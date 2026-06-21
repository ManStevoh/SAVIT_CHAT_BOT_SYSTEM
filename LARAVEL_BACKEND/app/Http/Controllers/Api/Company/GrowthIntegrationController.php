<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Growth\GrowthIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthIntegrationController extends Controller
{
    public function index(Request $request, GrowthIntegrationService $integrations): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return response()->json(['integrations' => $integrations->statusForCompany($company)]);
    }

    public function connect(Request $request, GrowthIntegrationService $integrations): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'provider' => 'required|string|in:ga4,email,website',
            'siteUrl' => 'nullable|url|max:500',
            'measurementId' => 'nullable|string|max:64',
        ]);

        $config = array_filter([
            'site_url' => $validated['siteUrl'] ?? null,
            'measurement_id' => $validated['measurementId'] ?? null,
        ]);

        $row = $integrations->connect($company, $validated['provider'], $config);

        return response()->json([
            'success' => true,
            'integration' => [
                'provider' => $row->provider,
                'status' => $row->status,
            ],
        ]);
    }

    public function sync(Request $request, GrowthIntegrationService $integrations): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $results = $integrations->syncCompany($company);

        return response()->json(['success' => true, 'results' => $results]);
    }
}
