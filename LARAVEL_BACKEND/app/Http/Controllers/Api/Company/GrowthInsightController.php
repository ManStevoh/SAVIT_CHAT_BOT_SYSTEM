<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\GrowthInsight;
use App\Services\Growth\GrowthInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthInsightController extends Controller
{
    public function index(Request $request, GrowthInsightService $insightService): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $insights = GrowthInsight::where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($i) => $insightService->formatInsight($i));

        return response()->json($insights->values()->all());
    }

    public function generate(Request $request, GrowthInsightService $insightService): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $insights = $insightService->generateInsights($company);

        return response()->json(['success' => true, 'insights' => $insights]);
    }

    public function markRead(Request $request, GrowthInsight $insight): JsonResponse
    {
        if ((int) $request->user()->company_id !== (int) $insight->company_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $insight->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }
}
