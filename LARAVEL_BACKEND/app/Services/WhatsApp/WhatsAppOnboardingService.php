<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class WhatsAppOnboardingService
{
    public function __construct(
        protected WhatsAppGraphClient $graph
    ) {}

    /**
     * Complete embedded signup: exchange token, subscribe webhooks, register phone, persist account.
     *
     * @return array{success: bool, message?: string, account?: WhatsAppAccount, phoneNumberId?: string|null}
     */
    public function completeSignup(
        int $companyId,
        ?string $code,
        ?string $phoneNumberId,
        ?string $wabaId,
        ?string $displayPhone
    ): array {
        if ($code === null || $code === '') {
            return [
                'success' => false,
                'message' => 'Authorization code missing. Please complete the Meta signup popup.',
            ];
        }

        $accessToken = $this->graph->exchangeCodeForToken($code);
        if ($accessToken === null || $accessToken === '') {
            return [
                'success' => false,
                'message' => 'Failed to exchange Meta authorization code. Check super-admin WhatsApp app credentials.',
            ];
        }

        $phoneNumberId = (string) ($phoneNumberId ?? '');
        $wabaId = (string) ($wabaId ?? '');
        $qualityRating = null;

        if ($phoneNumberId === '' || $wabaId === '') {
            $discovered = $this->graph->discoverPhoneData($accessToken);
            $phoneNumberId = $phoneNumberId !== '' ? $phoneNumberId : (string) ($discovered['phoneNumberId'] ?? '');
            $wabaId = $wabaId !== '' ? $wabaId : (string) ($discovered['whatsappBusinessAccountId'] ?? '');
            $displayPhone = $displayPhone ?? ($discovered['displayPhoneNumber'] ?? null);
            $qualityRating = $discovered['qualityRating'] ?? null;
        }

        if ($phoneNumberId === '') {
            return [
                'success' => false,
                'message' => 'Phone Number ID not received from Meta. Please retry embedded signup.',
            ];
        }

        return $this->activateAccount(
            $companyId,
            $phoneNumberId,
            $accessToken,
            $wabaId !== '' ? $wabaId : null,
            $displayPhone,
            $qualityRating,
        );
    }

    /**
     * Connect using credentials from Meta Developer Console (Phone Number ID + permanent access token).
     *
     * @return array{success: bool, message?: string, account?: WhatsAppAccount, phoneNumberId?: string|null}
     */
    public function completeManualConnect(
        int $companyId,
        string $phoneNumberId,
        string $accessToken,
        ?string $wabaId,
        ?string $displayPhone
    ): array {
        $phoneNumberId = trim($phoneNumberId);
        $accessToken = trim($accessToken);

        if ($phoneNumberId === '' || $accessToken === '') {
            return [
                'success' => false,
                'message' => 'Phone Number ID and access token are required.',
            ];
        }

        if ($this->isPhoneUsedByAnotherCompany($phoneNumberId, $companyId)) {
            return [
                'success' => false,
                'message' => 'This phone number is already connected to another company on the platform.',
            ];
        }

        $phoneData = $this->graph->verifyPhoneNumber($phoneNumberId, $accessToken);
        if ($phoneData === null) {
            return [
                'success' => false,
                'message' => 'Could not verify the access token with Meta. Check Phone Number ID and token permissions (whatsapp_business_messaging, whatsapp_business_management).',
            ];
        }

        $wabaId = trim((string) ($wabaId ?? ''));
        $displayPhone = $displayPhone ?? ($phoneData['display_phone_number'] ?? null);
        $qualityRating = $phoneData['quality_rating'] ?? null;

        if ($wabaId === '') {
            $discovered = $this->graph->discoverPhoneData($accessToken);
            $wabaId = (string) ($discovered['whatsappBusinessAccountId'] ?? '');
            $displayPhone = $displayPhone ?? ($discovered['displayPhoneNumber'] ?? null);
            $qualityRating = $qualityRating ?? ($discovered['qualityRating'] ?? null);
        }

        return $this->activateAccount(
            $companyId,
            $phoneNumberId,
            $accessToken,
            $wabaId !== '' ? $wabaId : null,
            $displayPhone,
            $qualityRating,
        );
    }

    public function disconnect(WhatsAppAccount $account): array
    {
        $wabaId = (string) ($account->whatsapp_business_account_id ?? '');
        $token = $account->access_token;

        if ($wabaId !== '' && $token !== '') {
            $result = $this->graph->unsubscribeWabaWebhooks($wabaId, $token);
            if (! $result['ok']) {
                Log::info('WhatsApp webhook unsubscribe returned non-success', [
                    'waba_id' => $wabaId,
                    'status' => $result['status'],
                    'body' => $result['body'],
                ]);
            }
        }

        $account->update([
            'status' => 'inactive',
            'onboarding_status' => 'disconnected',
            'disconnected_at' => now(),
        ]);

        return ['success' => true, 'message' => 'WhatsApp disconnected.'];
    }

    /**
     * @return array{success: bool, message?: string, account?: WhatsAppAccount, phoneNumberId?: string|null}
     */
    protected function activateAccount(
        int $companyId,
        string $phoneNumberId,
        string $accessToken,
        ?string $wabaId,
        ?string $displayPhone,
        ?string $qualityRating,
    ): array {
        $pin = $this->generateRegistrationPin();

        $account = WhatsAppAccount::updateOrCreate(
            ['company_id' => $companyId],
            [
                'phone_number_id' => $phoneNumberId,
                'access_token' => $accessToken,
                'display_phone_number' => $displayPhone,
                'whatsapp_business_account_id' => $wabaId,
                'status' => 'inactive',
                'onboarding_status' => 'token_received',
                'onboarding_error' => null,
                'quality_rating' => $qualityRating,
                'registration_pin' => Crypt::encryptString($pin),
                'connected_at' => now(),
                'disconnected_at' => null,
            ]
        );

        if ($wabaId !== null && $wabaId !== '') {
            $subscribe = $this->graph->subscribeWabaWebhooks($wabaId, $accessToken);
            if ($subscribe['ok'] || $this->graph->isAlreadySubscribedError($subscribe)) {
                $account->webhook_subscribed_at = now();
                $account->onboarding_status = 'webhook_subscribed';
            } else {
                $error = $subscribe['data']['error']['message'] ?? 'Webhook subscription failed';
                $account->onboarding_status = 'error';
                $account->onboarding_error = $error;
                $account->save();

                Log::warning('WhatsApp webhook subscribe failed', ['company_id' => $companyId, 'error' => $error]);

                return [
                    'success' => false,
                    'message' => 'Connected to Meta but webhook subscription failed: ' . $error,
                    'account' => $account,
                ];
            }
        }

        $register = $this->graph->registerPhoneNumber($phoneNumberId, $accessToken, $pin);
        if ($register['ok'] || $this->graph->isAlreadyRegisteredError($register)) {
            $account->phone_registered_at = now();
            $account->onboarding_status = 'active';
            $account->status = 'active';
        } else {
            $error = $register['data']['error']['message'] ?? 'Phone registration failed';
            $account->onboarding_status = 'error';
            $account->onboarding_error = $error;
            $account->save();

            Log::warning('WhatsApp phone register failed', ['company_id' => $companyId, 'error' => $error]);

            return [
                'success' => false,
                'message' => 'Meta authorized but phone registration failed: ' . $error,
                'account' => $account,
            ];
        }

        $account->save();

        return [
            'success' => true,
            'message' => 'WhatsApp connected successfully. You can now receive and send messages.',
            'account' => $account->fresh(),
            'phoneNumberId' => $phoneNumberId,
        ];
    }

    protected function isPhoneUsedByAnotherCompany(string $phoneNumberId, int $companyId): bool
    {
        return WhatsAppAccount::query()
            ->where('phone_number_id', $phoneNumberId)
            ->where('company_id', '!=', $companyId)
            ->where('status', 'active')
            ->exists();
    }

    protected function generateRegistrationPin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
