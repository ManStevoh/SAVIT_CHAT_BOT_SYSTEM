<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    /**
     * Connect company's WhatsApp Business number via Meta Cloud API.
     * Requires phone_number_id and access_token from Meta for Developers dashboard.
     */
    public function connect(Request $request): JsonResponse
    {
        $request->validate([
            'phoneNumberId' => 'required|string|max:100',
            'accessToken' => 'required|string',
            'displayPhoneNumber' => 'nullable|string|max:30',
            'whatsappBusinessAccountId' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $companyId = $user->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $account = WhatsAppAccount::updateOrCreate(
            ['company_id' => $companyId],
            [
                'phone_number_id' => $request->phoneNumberId,
                'access_token' => $request->accessToken,
                'display_phone_number' => $request->displayPhoneNumber,
                'whatsapp_business_account_id' => $request->whatsappBusinessAccountId,
                'status' => 'active',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp account connected. Set your webhook URL in Meta App to: ' . url('/api/whatsapp/webhook'),
        ]);
    }

    /**
     * Disconnect (deactivate) WhatsApp for the company.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        WhatsAppAccount::where('company_id', $companyId)->update(['status' => 'inactive']);

        return response()->json(['success' => true, 'message' => 'WhatsApp disconnected.']);
    }

    /**
     * Get current connection status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        if (! $companyId) {
            return response()->json(['connected' => false, 'phoneNumberId' => null]);
        }

        $account = WhatsAppAccount::where('company_id', $companyId)->where('status', 'active')->first();
        return response()->json([
            'connected' => (bool) $account,
            'phoneNumberId' => $account?->phone_number_id,
            'displayPhoneNumber' => $account?->display_phone_number,
        ]);
    }

    /**
     * List WhatsApp numbers connected to the company.
     * GET /api/company/whatsapp/numbers
     */
    public function numbers(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $accounts = WhatsAppAccount::where('company_id', $companyId)
            ->get(['id', 'phone_number_id', 'display_phone_number', 'status']);

        $items = $accounts->map(fn ($a) => [
            'id' => (string) $a->id,
            'phoneNumberId' => $a->phone_number_id,
            'displayPhoneNumber' => $a->display_phone_number ?? $a->phone_number_id,
            'status' => $a->status,
        ]);

        return response()->json($items);
    }
}
