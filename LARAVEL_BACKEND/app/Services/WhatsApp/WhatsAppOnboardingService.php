<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        $pin = $this->generateRegistrationPin();

        $account = WhatsAppAccount::updateOrCreate(
            ['company_id' => $companyId],
            [
                'phone_number_id' => $phoneNumberId,
                'access_token' => $accessToken,
                'display_phone_number' => $displayPhone,
                'whatsapp_business_account_id' => $wabaId !== '' ? $wabaId : null,
                'status' => 'inactive',
                'onboarding_status' => 'token_received',
                'onboarding_error' => null,
                'quality_rating' => $qualityRating,
                'registration_pin' => Crypt::encryptString($pin),
                'connected_at' => now(),
                'disconnected_at' => null,
            ]
        );

        if ($wabaId !== '') {
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

    protected function generateRegistrationPin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
