<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Models\CompanyApiKey;
use App\Models\WebhookEndpoint;
use App\Services\Platform\ApiKeyService;
use App\Services\Platform\WebhookDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiPlatformController extends Controller
{
    public function listApiKeys(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $keys = CompanyApiKey::where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'key_prefix', 'scopes', 'last_used_at', 'revoked_at', 'created_at']);

        return response()->json(['apiKeys' => $keys]);
    }

    public function createApiKey(Request $request, ApiKeyService $apiKeys): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company || $request->user()->role !== 'company_owner') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! \App\Services\PlanLimitService::companyHasApiAccess($company)) {
            return response()->json([
                'message' => 'API access is available on Growth and Enterprise plans. Upgrade to create API keys.',
                'code' => 'api_access_required',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:40',
        ]);

        $result = $apiKeys->create(
            $company,
            $request->user(),
            $validated['name'],
            $validated['scopes'] ?? ['read'],
        );

        return response()->json([
            'apiKey' => $result['key'],
            'plainText' => $result['plain_text'],
            'message' => 'Store this key securely — it will not be shown again.',
        ], 201);
    }

    public function revokeApiKey(Request $request, int $id, ApiKeyService $apiKeys): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company || $request->user()->role !== 'company_owner') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $key = CompanyApiKey::where('company_id', $company->id)->findOrFail($id);
        $apiKeys->revoke($key, $request->user());

        return response()->json(['success' => true]);
    }

    public function listWebhooks(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $endpoints = WebhookEndpoint::where('company_id', $company->id)->orderByDesc('id')->get();

        return response()->json(['webhooks' => $endpoints]);
    }

    public function createWebhook(Request $request, WebhookDeliveryService $webhooks): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company || $request->user()->role !== 'company_owner') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'string|max:80',
        ]);

        $endpoint = $webhooks->createEndpoint($company, $validated['url'], $validated['events']);

        return response()->json(['webhook' => $endpoint], 201);
    }

    public function billingHistory(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $payments = BillingPayment::where('company_id', $company->id)
            ->orderByDesc('paid_at')
            ->limit(50)
            ->get();

        return response()->json(['payments' => $payments]);
    }
}
