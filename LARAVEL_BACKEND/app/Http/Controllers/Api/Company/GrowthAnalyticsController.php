<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Growth\GrowthAnalyticsService;
use App\Services\Growth\GrowthDemoDataService;
use App\Services\Growth\GrowthLimitService;
use App\Services\Growth\GrowthOptimizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthAnalyticsController extends Controller
{
    public function index(
        Request $request,
        GrowthAnalyticsService $analytics,
        GrowthOptimizerService $optimizer,
        GrowthDemoDataService $demo
    ): JsonResponse {
        $companyId = $this->companyId($request);
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $company = $request->user()->company;
        $period = $request->input('period', '30d');
        $summary = $analytics->executiveSummary($companyId, $period);

        if ($company && $demo->shouldUseDemo($company, $summary)) {
            $demoData = $demo->demoAnalytics($period);

            return response()->json(array_merge($demoData, [
                'contentIntelligence' => $analytics->contentIntelligence($companyId),
                'limits' => $this->limitsForUser($request),
                'intelligence' => $optimizer->intelligenceSummary($company),
                'celebration' => $this->celebrationPayload($company),
            ]));
        }

        return response()->json([
            'isDemo' => false,
            'summary' => $summary,
            'platformBreakdown' => $analytics->platformBreakdown($companyId, $period),
            'topPosts' => $analytics->topPerformingPosts($companyId, $period),
            'contentIntelligence' => $analytics->contentIntelligence($companyId),
            'funnel' => $analytics->funnelMetrics($companyId, $period),
            'limits' => $this->limitsForUser($request),
            'intelligence' => $company ? $optimizer->intelligenceSummary($company) : null,
            'celebration' => $company ? $this->celebrationPayload($company) : null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function celebrationPayload(\App\Models\Company $company): ?array
    {
        if (! $company->first_attributed_sale_at) {
            return null;
        }

        $recent = $company->first_attributed_sale_at->isAfter(now()->subDays(14));

        return [
            'firstAttributedSaleAt' => $company->first_attributed_sale_at->toIso8601String(),
            'showHighlight' => $recent,
            'message' => 'Your first sale was attributed to a social post. The Growth loop is proven!',
        ];
    }

    private function companyId(Request $request): ?int
    {
        return $request->user()->company_id;
    }

    private function limitsForUser(Request $request): array
    {
        $company = $request->user()->company;
        if (! $company) {
            return ['aiPostsUsed' => 0, 'aiPostsLimit' => 0, 'aiImagesUsed' => 0, 'aiImagesLimit' => 0, 'platformLimit' => 0];
        }

        return [
            'aiPostsUsed' => GrowthLimitService::aiPostsUsedThisMonth($company),
            'aiPostsLimit' => GrowthLimitService::getAiPostsLimit($company),
            'aiImagesUsed' => GrowthLimitService::aiImagesUsedThisMonth($company),
            'aiImagesLimit' => GrowthLimitService::getAiImagesLimit($company),
            'platformLimit' => GrowthLimitService::getPlatformLimit($company),
        ];
    }
}
