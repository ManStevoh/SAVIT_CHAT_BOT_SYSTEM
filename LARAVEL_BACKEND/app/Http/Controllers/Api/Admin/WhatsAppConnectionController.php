<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppBillingModel;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppConnectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WhatsAppAccount::with('company:id,name,email,status')
            ->orderByDesc('connected_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('onboarding') && $request->onboarding !== 'all') {
            $query->where('onboarding_status', $request->onboarding);
        }

        $accounts = $query->get();

        $data = $accounts->map(fn (WhatsAppAccount $a) => [
            'id' => (string) $a->id,
            'companyId' => (string) $a->company_id,
            'companyName' => $a->company?->name,
            'companyEmail' => $a->company?->email,
            'companyStatus' => $a->company?->status,
            'phoneNumberId' => $a->phone_number_id,
            'displayPhoneNumber' => $a->display_phone_number,
            'whatsappBusinessAccountId' => $a->whatsapp_business_account_id,
            'status' => $a->status,
            'onboardingStatus' => $a->onboarding_status,
            'onboardingError' => $a->onboarding_error,
            'displayNameStatus' => $a->display_name_status,
            'qualityRating' => $a->quality_rating,
            'webhookSubscribedAt' => $a->webhook_subscribed_at?->toIso8601String(),
            'creditLineSharedAt' => $a->credit_line_shared_at?->toIso8601String(),
            'metaBillingModel' => $a->meta_billing_model,
            'creditAllocationConfigId' => $a->credit_allocation_config_id,
            'phoneRegisteredAt' => $a->phone_registered_at?->toIso8601String(),
            'connectedAt' => $a->connected_at?->toIso8601String(),
            'disconnectedAt' => $a->disconnected_at?->toIso8601String(),
        ]);

        return response()->json([
            'connections' => $data->values()->all(),
            'platform' => [
                'embeddedSignupEnabled' => WhatsAppPlatformConfig::isEmbeddedSignupEnabled(),
                'embeddedSignupReady' => WhatsAppPlatformConfig::hasEmbeddedSignupCredentials(),
                'manualConnectEnabled' => WhatsAppPlatformConfig::isManualConnectEnabled(),
                'webhookUrl' => WhatsAppPlatformConfig::webhookCallbackUrl(),
                'graphVersion' => WhatsAppPlatformConfig::GRAPH_VERSION,
                'enableCoexist' => WhatsAppPlatformConfig::enableCoexist(),
                'billingModel' => WhatsAppPlatformConfig::billingModel(),
                'billingModelLabel' => WhatsAppBillingModel::label(WhatsAppPlatformConfig::billingModel()),
                'solutionPartnerReady' => WhatsAppPlatformConfig::isSolutionPartnerBillingReady(),
            ],
        ]);
    }
}
