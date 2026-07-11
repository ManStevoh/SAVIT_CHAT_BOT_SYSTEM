<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Agent\Brain\UnifiedCompanyBrainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyBrainController extends Controller
{
    public function show(Request $request, UnifiedCompanyBrainService $brain): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $snapshot = $brain->refreshIfStale($company, 30);

        if (! $snapshot) {
            return response()->json(['message' => 'Brain snapshot unavailable.'], 503);
        }

        return response()->json([
            'snapshot' => [
                'id' => $snapshot->id,
                'snapshotAt' => $snapshot->snapshot_at?->toIso8601String(),
                'summaryText' => $snapshot->summary_text,
                'commerceData' => $snapshot->commerce_data,
                'growthData' => $snapshot->growth_data,
                'digest' => $snapshot->digest,
            ],
        ]);
    }

    public function refresh(Request $request, UnifiedCompanyBrainService $brain): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $snapshot = $brain->buildSnapshot($company);

        return response()->json([
            'snapshot' => [
                'id' => $snapshot->id,
                'snapshotAt' => $snapshot->snapshot_at?->toIso8601String(),
                'summaryText' => $snapshot->summary_text,
                'digest' => $snapshot->digest,
            ],
        ], 201);
    }
}
