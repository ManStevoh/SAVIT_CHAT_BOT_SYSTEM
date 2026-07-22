<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppOnboardingService;
use App\Services\WhatsApp\WhatsAppBillingModel;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(
        protected WhatsAppOnboardingService $onboarding
    ) {}

    protected function billingNotReadyResponse(): ?JsonResponse
    {
        if (! WhatsAppPlatformConfig::isSolutionPartnerBilling()) {
            return null;
        }

        if (WhatsAppPlatformConfig::isSolutionPartnerBillingReady()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Platform WhatsApp billing is enabled but not configured. Contact your platform administrator.',
            'code' => 'platform_billing_not_ready',
        ], 503);
    }

    public function embeddedConfig(): JsonResponse
    {
        $enabled = WhatsAppPlatformConfig::isEmbeddedSignupEnabled();

        return response()->json([
            'enabled' => $enabled,
            'appId' => $enabled ? WhatsAppPlatformConfig::embeddedAppId() : null,
            'configId' => $enabled ? WhatsAppPlatformConfig::embeddedConfigId() : null,
            'graphVersion' => WhatsAppPlatformConfig::GRAPH_VERSION,
            'enableCoexist' => WhatsAppPlatformConfig::enableCoexist(),
            'webhookUrl' => WhatsAppPlatformConfig::webhookCallbackUrl(),
            'metaBillingModel' => WhatsAppPlatformConfig::billingModel(),
            'requiresMetaPaymentMethod' => ! WhatsAppPlatformConfig::isSolutionPartnerBilling(),
            'platformBillingReady' => WhatsAppPlatformConfig::isSolutionPartnerBilling()
                ? WhatsAppPlatformConfig::isSolutionPartnerBillingReady()
                : true,
        ]);
    }

    public function connect(Request $request): JsonResponse
    {
        if ($billingBlock = $this->billingNotReadyResponse()) {
            return $billingBlock;
        }

        if (! WhatsAppPlatformConfig::isManualConnectEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Manual WhatsApp connection is disabled by the platform administrator.',
            ], 403);
        }

        $validated = $request->validate([
            'phoneNumberId' => 'required|string|max:100',
            'accessToken' => 'required|string|max:2000',
            'displayPhoneNumber' => 'nullable|string|max:30',
            'whatsappBusinessAccountId' => 'nullable|string|max:100',
            'registrationPin' => 'nullable|digits:6',
        ]);

        $user = $request->user();
        $companyId = $user->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $result = $this->onboarding->completeManualConnect(
            (int) $companyId,
            $validated['phoneNumberId'],
            $validated['accessToken'],
            $validated['whatsappBusinessAccountId'] ?? null,
            $validated['displayPhoneNumber'] ?? null,
            $validated['registrationPin'] ?? null,
        );

        $status = $result['success'] ? 200 : 422;
        $account = $result['account'] ?? null;

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
            'phoneNumberId' => $result['phoneNumberId'] ?? $account?->phone_number_id,
            'whatsappBusinessAccountId' => $account?->whatsapp_business_account_id,
            'displayPhoneNumber' => $account?->display_phone_number,
            'onboardingStatus' => $account?->onboarding_status,
        ], $status);
    }

    public function completeEmbeddedSignup(Request $request): JsonResponse
    {
        if ($billingBlock = $this->billingNotReadyResponse()) {
            return $billingBlock;
        }

        if (! WhatsAppPlatformConfig::isEmbeddedSignupEnabled()) {
            $message = WhatsAppPlatformConfig::hasEmbeddedSignupCredentials()
                ? 'WhatsApp embedded signup is temporarily disabled by the platform administrator.'
                : 'WhatsApp embedded signup is not configured. Contact your platform administrator.';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 503);
        }

        $validated = $request->validate([
            'code' => 'required|string',
            'phoneNumberId' => 'nullable|string|max:100',
            'whatsappBusinessAccountId' => 'nullable|string|max:100',
            'displayPhoneNumber' => 'nullable|string|max:30',
        ]);

        $user = $request->user();
        $companyId = $user->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $result = $this->onboarding->completeSignup(
            (int) $companyId,
            $validated['code'],
            $validated['phoneNumberId'] ?? null,
            $validated['whatsappBusinessAccountId'] ?? null,
            $validated['displayPhoneNumber'] ?? null,
        );

        $status = $result['success'] ? 200 : 422;
        $account = $result['account'] ?? null;

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
            'phoneNumberId' => $result['phoneNumberId'] ?? $account?->phone_number_id,
            'whatsappBusinessAccountId' => $account?->whatsapp_business_account_id,
            'displayPhoneNumber' => $account?->display_phone_number,
            'onboardingStatus' => $account?->onboarding_status,
        ], $status);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $account = WhatsAppAccount::where('company_id', $companyId)->where('status', 'active')->first();
        if (! $account) {
            WhatsAppAccount::where('company_id', $companyId)->update(['status' => 'inactive']);

            return response()->json(['success' => true, 'message' => 'WhatsApp disconnected.']);
        }

        $result = $this->onboarding->disconnect($account);

        return response()->json($result);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        if (! $companyId) {
            return response()->json(['connected' => false, 'phoneNumberId' => null]);
        }

        $account = WhatsAppAccount::where('company_id', $companyId)
            ->where('status', 'active')
            ->first();

        return response()->json([
            'connected' => (bool) $account,
            'phoneNumberId' => $account?->phone_number_id,
            'displayPhoneNumber' => $account?->display_phone_number,
            'onboardingStatus' => $account?->onboarding_status,
            'displayNameStatus' => $account?->display_name_status,
            'qualityRating' => $account?->quality_rating,
            'webhookSubscribed' => $account?->webhook_subscribed_at !== null,
            'phoneRegistered' => $account?->phone_registered_at !== null,
            'creditLineShared' => $account?->credit_line_shared_at !== null,
            'onboardingError' => $account?->onboarding_error,
            'embeddedSignupEnabled' => WhatsAppPlatformConfig::isEmbeddedSignupEnabled(),
            'manualConnectEnabled' => WhatsAppPlatformConfig::isManualConnectEnabled(),
            'webhookUrl' => WhatsAppPlatformConfig::webhookCallbackUrl(),
            'metaBillingModel' => WhatsAppPlatformConfig::billingModel(),
            'metaBillingModelLabel' => WhatsAppBillingModel::label(WhatsAppPlatformConfig::billingModel()),
            'requiresMetaPaymentMethod' => ! WhatsAppPlatformConfig::isSolutionPartnerBilling(),
            'platformBillingReady' => WhatsAppPlatformConfig::isSolutionPartnerBilling()
                ? WhatsAppPlatformConfig::isSolutionPartnerBillingReady()
                : true,
        ]);
    }

    public function numbers(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $accounts = WhatsAppAccount::where('company_id', $companyId)
            ->orderByDesc('connected_at')
            ->get([
                'id',
                'phone_number_id',
                'display_phone_number',
                'status',
                'onboarding_status',
                'display_name_status',
                'quality_rating',
                'connected_at',
            ]);

        $items = $accounts->map(fn ($a) => [
            'id' => (string) $a->id,
            'phoneNumberId' => $a->phone_number_id,
            'displayPhoneNumber' => $a->display_phone_number ?? $a->phone_number_id,
            'status' => $a->status,
            'onboardingStatus' => $a->onboarding_status,
            'displayNameStatus' => $a->display_name_status,
            'qualityRating' => $a->quality_rating,
            'connectedAt' => $a->connected_at?->toIso8601String(),
        ]);

        return response()->json($items);
    }
}
