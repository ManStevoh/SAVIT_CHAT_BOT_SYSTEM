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
        $summary = $this->storefront->analyticsSummary($company, $days);

        return response()->json(array_merge([
            'days' => $days,
            'funnel' => [
                'view_catalog' => $summary['view_catalog'],
                'view_product' => $summary['view_product'],
                'add_to_cart' => $summary['add_to_cart'],
                'begin_checkout' => $summary['begin_checkout'],
                'purchase' => $summary['purchase'],
            ],
        ], $summary));
    }
}
