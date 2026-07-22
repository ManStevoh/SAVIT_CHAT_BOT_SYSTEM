<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class WhatsAppOnboardingService
{
    public function __construct(
        protected WhatsAppGraphClient $graph,
        protected WhatsAppCreditSharingService $creditSharing,
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

        if ($this->isPhoneUsedByAnotherCompany($phoneNumberId, $companyId)) {
            return [
                'success' => false,
                'message' => 'This phone number is already connected to another company on the platform.',
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
        ?string $displayPhone,
        ?string $registrationPin = null,
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
            $registrationPin,
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

        if ($account->meta_billing_model === WhatsAppBillingModel::SOLUTION_PARTNER) {
            $revoke = $this->creditSharing->revokeForAccount($account);
            if (! $revoke['success']) {
                Log::warning('WhatsApp credit line revoke on disconnect failed', [
                    'company_id' => $account->company_id,
                    'allocation_config_id' => $account->credit_allocation_config_id,
                    'message' => $revoke['message'] ?? null,
                ]);
            }
        }

        $account->update([
            'status' => 'inactive',
            'onboarding_status' => 'disconnected',
            'disconnected_at' => now(),
            'credit_allocation_config_id' => null,
            'credit_line_shared_at' => null,
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
        ?string $registrationPin = null,
    ): array {
        $pin = $this->normalizeRegistrationPin($registrationPin) ?? $this->generateRegistrationPin();
        $billingModel = WhatsAppPlatformConfig::billingModel();

        $account = WhatsAppAccount::updateOrCreate(
            ['company_id' => $companyId],
            [
                'phone_number_id' => $phoneNumberId,
                'access_token' => $accessToken,
                'display_phone_number' => $displayPhone,
                'whatsapp_business_account_id' => $wabaId,
                'meta_billing_model' => $billingModel,
                'status' => 'inactive',
                'onboarding_status' => 'token_received',
                'onboarding_error' => null,
                'quality_rating' => $qualityRating,
                'registration_pin' => Crypt::encryptString($pin),
                'connected_at' => now(),
                'disconnected_at' => null,
                'credit_allocation_config_id' => null,
                'credit_line_shared_at' => null,
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

        if ($billingModel === WhatsAppBillingModel::SOLUTION_PARTNER) {
            if ($wabaId === null || $wabaId === '') {
                $account->onboarding_status = 'error';
                $account->onboarding_error = 'WhatsApp Business Account ID is required for platform billing.';
                $account->save();

                return [
                    'success' => false,
                    'message' => 'Solution Partner billing requires a WhatsApp Business Account ID from Meta signup.',
                    'account' => $account,
                ];
            }

            if (! WhatsAppPlatformConfig::isSolutionPartnerBillingReady()) {
                $account->onboarding_status = 'error';
                $account->onboarding_error = 'Platform Solution Partner billing is not fully configured.';
                $account->save();

                return [
                    'success' => false,
                    'message' => 'Platform billing is enabled but not configured. Contact your administrator to set credit line credentials in Admin → Settings.',
                    'account' => $account,
                ];
            }

            $creditShare = $this->creditSharing->shareCreditLineWithWaba($wabaId);
            if (! $creditShare['success']) {
                $account->onboarding_status = 'error';
                $account->onboarding_error = $creditShare['message'] ?? 'Credit line sharing failed';
                $account->save();

                return [
                    'success' => false,
                    'message' => $creditShare['message'] ?? 'Failed to attach platform credit line to your WhatsApp account.',
                    'account' => $account,
                ];
            }

            $account->credit_line_shared_at = now();
            $account->credit_allocation_config_id = $creditShare['allocationConfigId'] ?? null;
            $account->onboarding_status = 'credit_line_shared';
            $account->save();
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

            $message = 'Meta authorized but phone registration failed: '.$error;
            if (str_contains(strtolower($error), 'pin')) {
                $message .= ' Enter the existing 6-digit two-step verification PIN from WhatsApp Manager, or turn off two-step verification and try again.';
            }

            return [
                'success' => false,
                'message' => $message,
                'account' => $account,
            ];
        }

        $account->save();

        $successMessage = $billingModel === WhatsAppBillingModel::SOLUTION_PARTNER
            ? 'WhatsApp connected successfully. WhatsApp usage is billed through the platform — no Meta payment method required.'
            : 'WhatsApp connected successfully. You can now receive and send messages.';

        return [
            'success' => true,
            'message' => $successMessage,
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

    protected function normalizeRegistrationPin(?string $pin): ?string
    {
        if ($pin === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $pin) ?? '';
        if (strlen($digits) !== 6) {
            return null;
        }

        return $digits;
    }
}
