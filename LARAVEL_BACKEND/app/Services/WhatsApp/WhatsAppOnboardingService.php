<?php

namespace App\Services\WhatsApp;

use App\Models\Company;
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
        ?string $webhookVerifyToken = null,
        ?string $metaAppSecret = null,
    ): array {
        $phoneNumberId = trim($phoneNumberId);
        $accessToken = trim($accessToken);
        $metaAppSecret = trim((string) ($metaAppSecret ?? ''));
        $webhookVerifyToken = trim((string) ($webhookVerifyToken ?? ''));

        if ($phoneNumberId === '' || $accessToken === '') {
            return [
                'success' => false,
                'message' => 'Phone Number ID and access token are required.',
            ];
        }

        if ($metaAppSecret === '') {
            return [
                'success' => false,
                'message' => 'Meta App Secret is required for manual connection. Use the App Secret from the same Meta Developer app that created this access token (Meta → App settings → Basic). Do not use the platform/super-admin App Secret unless this token is from that same app.',
            ];
        }

        if ($webhookVerifyToken === '') {
            return [
                'success' => false,
                'message' => 'Webhook verify token is required for manual connection. Set any string in Meta → Your App → WhatsApp → Configuration → Webhook verify token, and paste the same value here. Do not reuse the platform/super-admin verify token unless this token is from that same app.',
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
            $webhookVerifyToken,
            $metaAppSecret,
            'manual',
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
            'webhook_subscribed_at' => null,
            'phone_registered_at' => null,
            'credit_allocation_config_id' => null,
            'credit_line_shared_at' => null,
            'meta_app_secret' => null,
            'verify_token' => null,
        ]);

        return ['success' => true, 'message' => 'WhatsApp disconnected.'];
    }

    /**
     * Repair inbound messaging for an already-connected account by (re)subscribing the app to the WABA.
     *
     * @return array{success: bool, message?: string, account?: WhatsAppAccount}
     */
    public function resubscribeWebhooks(
        WhatsAppAccount $account,
        ?string $metaAppSecret = null,
        ?string $webhookVerifyToken = null,
    ): array {
        $token = (string) ($account->access_token ?? '');
        if ($token === '') {
            return [
                'success' => false,
                'message' => 'No access token stored for this WhatsApp connection. Disconnect and reconnect.',
            ];
        }

        $secret = trim((string) ($metaAppSecret ?? ''));
        if ($secret !== '') {
            $account->meta_app_secret = $secret;
            $account->connected_via = 'manual';
        }

        $verifyToken = trim((string) ($webhookVerifyToken ?? ''));
        if ($verifyToken !== '') {
            $account->verify_token = $verifyToken;
        }

        // Manual/BYO Meta apps must store their own App Secret + verify token.
        if ($account->isManualConnection() && ! $account->hasMetaAppSecret()) {
            $account->save();

            return [
                'success' => false,
                'message' => 'Meta App Secret is missing. Paste the App Secret from the same Meta Developer app that created your access token, then retry Fix inbound messages.',
                'account' => $account,
            ];
        }

        if ($account->isManualConnection() && trim((string) ($account->verify_token ?? '')) === '') {
            $account->save();

            return [
                'success' => false,
                'message' => 'Webhook verify token is missing. Paste the verify token from your Meta app webhook configuration, then retry Fix inbound messages.',
                'account' => $account,
            ];
        }

        $wabaId = trim((string) ($account->whatsapp_business_account_id ?? ''));
        if ($wabaId === '') {
            $discovered = $this->graph->discoverPhoneData($token);
            $wabaId = trim((string) ($discovered['whatsappBusinessAccountId'] ?? ''));
            if ($wabaId !== '') {
                $account->whatsapp_business_account_id = $wabaId;
                if (empty($account->display_phone_number) && ! empty($discovered['displayPhoneNumber'])) {
                    $account->display_phone_number = $discovered['displayPhoneNumber'];
                }
                if (empty($account->quality_rating) && ! empty($discovered['qualityRating'])) {
                    $account->quality_rating = $discovered['qualityRating'];
                }
            }
        }

        if ($wabaId === '') {
            return [
                'success' => false,
                'message' => 'WhatsApp Business Account ID is missing. Disconnect and reconnect with your WABA ID (Meta → WhatsApp → API Setup), or use Connect with Facebook.',
            ];
        }

        $subscribeVerifyToken = trim((string) ($account->verify_token ?? ''));
        if ($subscribeVerifyToken === '' && ! $account->isManualConnection()) {
            $subscribeVerifyToken = WhatsAppPlatformConfig::webhookVerifyToken();
        }

        $subscribe = $this->graph->subscribeWabaWebhooks(
            $wabaId,
            $token,
            $subscribeVerifyToken !== '' ? $subscribeVerifyToken : null,
        );
        if (! ($subscribe['ok'] || $this->graph->isAlreadySubscribedError($subscribe))) {
            $error = $subscribe['data']['error']['message'] ?? 'Webhook subscription failed';
            $account->onboarding_error = $error;
            $account->save();

            Log::warning('WhatsApp webhook resubscribe failed', [
                'company_id' => $account->company_id,
                'waba_id' => $wabaId,
                'error' => $error,
            ]);

            return [
                'success' => false,
                'message' => 'Webhook subscription failed: '.$error,
                'account' => $account,
            ];
        }

        $account->webhook_subscribed_at = now();
        $account->onboarding_error = null;
        if ($account->status === 'active') {
            $account->onboarding_status = 'active';
        }
        $account->save();

        return [
            'success' => true,
            'message' => 'Webhook subscribed. You should now receive inbound WhatsApp messages.',
            'account' => $account->fresh(),
        ];
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
        ?string $webhookVerifyToken = null,
        ?string $metaAppSecret = null,
        string $connectedVia = 'embedded',
    ): array {
        $company = Company::find($companyId);
        if (! $company) {
            return ['success' => false, 'message' => 'Company not found.'];
        }

        if (! \App\Services\PlanLimitService::canConnectWhatsApp($company, $phoneNumberId)) {
            $limit = \App\Services\PlanLimitService::getWhatsAppNumberLimitForPlan(
                \App\Services\PlanLimitService::getCurrentPlanSlug($company)
            );

            return [
                'success' => false,
                'message' => "WhatsApp number limit reached ({$limit}) for your plan. Upgrade or disconnect an existing number.",
            ];
        }

        $wabaId = trim((string) ($wabaId ?? ''));
        if ($wabaId === '') {
            return [
                'success' => false,
                'message' => 'WhatsApp Business Account ID is required so RelayIQ can receive inbound messages. Provide the WABA ID from Meta → WhatsApp → API Setup, or reconnect with Facebook.',
            ];
        }

        $companyVerifyToken = trim((string) ($webhookVerifyToken ?? ''));
        $companyAppSecret = trim((string) ($metaAppSecret ?? ''));
        $pin = $this->normalizeRegistrationPin($registrationPin) ?? $this->generateRegistrationPin();
        $connectedVia = in_array($connectedVia, ['manual', 'embedded'], true) ? $connectedVia : 'embedded';

        // Manual/BYO Meta apps always bill themselves via Meta (tech provider).
        // Platform Solution Partner credit sharing applies only to Embedded Signup.
        $billingModel = $connectedVia === 'manual'
            ? WhatsAppBillingModel::TECH_PROVIDER
            : WhatsAppPlatformConfig::billingModel();

        if ($connectedVia === 'manual' && $companyVerifyToken === '') {
            return [
                'success' => false,
                'message' => 'Webhook verify token is required for manual connection. Set it in Meta → Your App → WhatsApp → Configuration and paste the same value here.',
            ];
        }

        $subscribeVerifyToken = $companyVerifyToken !== ''
            ? $companyVerifyToken
            : WhatsAppPlatformConfig::webhookVerifyToken();

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
                'verify_token' => $companyVerifyToken !== '' ? $companyVerifyToken : null,
                // Embedded Signup uses the platform App Secret; clear any leftover company secret.
                'meta_app_secret' => $connectedVia === 'manual' && $companyAppSecret !== ''
                    ? $companyAppSecret
                    : null,
                'connected_via' => $connectedVia,
                'registration_pin' => Crypt::encryptString($pin),
                'connected_at' => now(),
                'disconnected_at' => null,
                'webhook_subscribed_at' => null,
                'phone_registered_at' => null,
                'credit_allocation_config_id' => null,
                'credit_line_shared_at' => null,
            ]
        );

        $subscribe = $this->graph->subscribeWabaWebhooks(
            $wabaId,
            $accessToken,
            $subscribeVerifyToken !== '' ? $subscribeVerifyToken : null,
        );
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
                'message' => 'Connected to Meta but webhook subscription failed: '.$error,
                'account' => $account,
            ];
        }

        // Platform Solution Partner credit sharing is only for Embedded Signup accounts.
        if ($connectedVia !== 'manual' && $billingModel === WhatsAppBillingModel::SOLUTION_PARTNER) {
            if ($wabaId === '') {
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

        $successMessage = $connectedVia === 'manual'
            ? 'WhatsApp connected successfully with your Meta app credentials. Inbound messages use your App Secret and verify token — not the platform app.'
            : ($billingModel === WhatsAppBillingModel::SOLUTION_PARTNER
                ? 'WhatsApp connected successfully. WhatsApp usage is billed through the platform — no Meta payment method required.'
                : 'WhatsApp connected successfully. You can now receive and send messages.');

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
