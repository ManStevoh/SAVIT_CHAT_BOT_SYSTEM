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

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:40',
        ]);

