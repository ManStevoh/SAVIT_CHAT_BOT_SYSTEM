<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Storefront\StorefrontService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontAnalyticsController extends Controller
{
    public function __construct(protected StorefrontService $storefront) {}

    public function show(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $company = Company::findOrFail($companyId);
        $days = max(1, min(90, (int) $request->query('days', 30)));

        return response()->json([
            'days' => $days,
            'funnel' => $this->storefront->analyticsSummary($company, $days),
        ]);
    }
}
