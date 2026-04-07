<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function embeddedConfig(): JsonResponse
    {
        $appId = (string) config('whatsapp.embedded_signup_app_id', '');
        $configId = (string) config('whatsapp.embedded_signup_config_id', '');

        return response()->json([
            'enabled' => $appId !== '' && $configId !== '',
            'appId' => $appId !== '' ? $appId : null,
            'configId' => $configId !== '' ? $configId : null,
            'graphVersion' => 'v21.0',
        ]);
    }

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
     * Complete WhatsApp Embedded Signup and connect number to the company.
     * Accepts the Meta OAuth "code" and optional phoneNumberId/wabaId from popup event.
     */
    public function completeEmbeddedSignup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string',
            'phoneNumberId' => 'nullable|string|max:100',
            'whatsappBusinessAccountId' => 'nullable|string|max:100',
            'displayPhoneNumber' => 'nullable|string|max:30',
            'accessToken' => 'nullable|string',
        ]);

        $user = $request->user();
        $companyId = $user->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $resolvedAccessToken = (string) ($validated['accessToken'] ?? '');
        if ($resolvedAccessToken === '') {
            $resolvedAccessToken = (string) config('whatsapp.default_access_token', '');
        }
        if ($resolvedAccessToken === '' && ! empty($validated['code'])) {
            $resolvedAccessToken = $this->exchangeCodeForAccessToken((string) $validated['code']) ?? '';
        }

        if ($resolvedAccessToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Unable to resolve access token. Configure WHATSAPP_DEFAULT_ACCESS_TOKEN or send accessToken/code.',
            ], 422);
        }

        $phoneNumberId = (string) ($validated['phoneNumberId'] ?? '');
        $wabaId = (string) ($validated['whatsappBusinessAccountId'] ?? '');
        $displayPhone = $validated['displayPhoneNumber'] ?? null;

        // If popup did not return IDs, try to discover one from Graph API.
        if ($phoneNumberId === '' || $wabaId === '') {
            $discovered = $this->discoverPhoneData($resolvedAccessToken);
            $phoneNumberId = $phoneNumberId !== '' ? $phoneNumberId : (string) ($discovered['phoneNumberId'] ?? '');
            $wabaId = $wabaId !== '' ? $wabaId : (string) ($discovered['whatsappBusinessAccountId'] ?? '');
            if ($displayPhone === null) {
                $displayPhone = $discovered['displayPhoneNumber'] ?? null;
            }
        }

        if ($phoneNumberId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Phone Number ID not received from Meta. Please retry embedded signup.',
            ], 422);
        }

        WhatsAppAccount::updateOrCreate(
            ['company_id' => $companyId],
            [
                'phone_number_id' => $phoneNumberId,
                'access_token' => $resolvedAccessToken,
                'display_phone_number' => $displayPhone,
                'whatsapp_business_account_id' => $wabaId !== '' ? $wabaId : null,
                'status' => 'active',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp connected via embedded signup.',
            'phoneNumberId' => $phoneNumberId,
            'whatsappBusinessAccountId' => $wabaId !== '' ? $wabaId : null,
            'displayPhoneNumber' => $displayPhone,
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

    protected function exchangeCodeForAccessToken(string $code): ?string
    {
        $appId = (string) config('whatsapp.embedded_signup_app_id', '');
        $appSecret = (string) config('whatsapp.embedded_signup_app_secret', '');
        $redirectUri = (string) config('whatsapp.embedded_signup_redirect_uri', '');
        if ($appId === '' || $appSecret === '' || $redirectUri === '') {
            return null;
        }

        try {
            $response = Http::timeout(20)->get('https://graph.facebook.com/v21.0/oauth/access_token', [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);
            if (! $response->successful()) {
                Log::warning('Embedded signup token exchange failed', ['status' => $response->status(), 'body' => $response->json()]);
                return null;
            }
            return $response->json('access_token');
        } catch (\Throwable $e) {
            Log::warning('Embedded signup token exchange error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Best-effort discovery using Graph API when popup callback misses IDs.
     */
    protected function discoverPhoneData(string $accessToken): array
    {
        try {
            $graphUrl = rtrim((string) config('whatsapp.graph_url', 'https://graph.facebook.com/v21.0'), '/');
            $resp = Http::withToken($accessToken)
                ->timeout(20)
                ->get($graphUrl . '/me/whatsapp_business_accounts', [
                    'fields' => 'id,name,phone_numbers{id,display_phone_number,verified_name}',
                    'limit' => 1,
                ]);
            if (! $resp->successful()) {
                return [];
            }
            $firstWaba = $resp->json('data.0');
            $firstPhone = $firstWaba['phone_numbers']['data'][0] ?? $firstWaba['phone_numbers'][0] ?? null;

            return [
                'whatsappBusinessAccountId' => $firstWaba['id'] ?? null,
                'phoneNumberId' => $firstPhone['id'] ?? null,
                'displayPhoneNumber' => $firstPhone['display_phone_number'] ?? null,
            ];
        } catch (\Throwable) {
            return [];
        }
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
