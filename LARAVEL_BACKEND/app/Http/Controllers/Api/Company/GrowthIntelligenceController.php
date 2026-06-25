<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Jobs\Growth\ExtractGrowthPatternsJob;
use App\Models\GrowthLearningPattern;
use App\Models\PortfolioRecommendation;
use App\Services\Growth\ContentPredictionService;
use App\Services\Growth\GrowthContentService;
use App\Services\Growth\GrowthOptimizerService;
use App\Services\Growth\GrowthPatternService;
use App\Services\Growth\CrossBrandLearningService;
use App\Services\Growth\GrowthAttributionExportService;
use App\Services\Growth\GrowthBenchmarkService;
use App\Services\Growth\GrowthPredictionExplainService;
use App\Services\Growth\GrowthStrategyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthIntelligenceController extends Controller
{
    public function patterns(Request $request, GrowthPatternService $patterns): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return response()->json(['patterns' => $patterns->patternsForCompany((int) $companyId)]);
    }

    public function extractPatterns(Request $request, GrowthPatternService $patterns): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $period = (int) $request->input('periodDays', 30);
        $items = $patterns->extractForCompany($company, $period);

        return response()->json(['success' => true, 'patterns' => $items]);
    }

    public function queueExtractPatterns(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        ExtractGrowthPatternsJob::dispatch((int) $companyId);

        return response()->json(['success' => true, 'message' => 'Pattern extraction queued.']);
    }

    public function contentMix(Request $request, GrowthStrategyService $strategy): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return response()->json(['plan' => $strategy->buildContentMixPlan($company)]);
    }

    public function weeklyBrief(Request $request, GrowthStrategyService $strategy, GrowthOptimizerService $optimizer): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        if ($request->boolean('refresh')) {
            $brief = $strategy->generateWeeklyBrief($company);
        } else {
            $summary = $optimizer->intelligenceSummary($company);
            if ($summary['weeklyBrief']) {
                return response()->json(['brief' => $summary['weeklyBrief']]);
            }
            $brief = $strategy->generateWeeklyBrief($company);
        }

        return response()->json([
            'brief' => [
                'id' => (string) $brief->id,
                'title' => $brief->title,
                'body' => $brief->body,
                'data' => $brief->data,
                'createdAt' => $brief->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function executeMixPlan(Request $request, GrowthOptimizerService $optimizer): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        try {
            $result = $optimizer->executeMixPlan($company);

            return response()->json([
                'success' => true,
                'plan' => $result['plan'],
                'posts' => $result['posts'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function intelligenceSummary(Request $request, GrowthOptimizerService $optimizer): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return response()->json($optimizer->intelligenceSummary($company));
    }

    public function scoreDrafts(Request $request, ContentPredictionService $prediction): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return response()->json(['drafts' => $prediction->scoreCompanyDrafts((int) $companyId)]);
    }

    public function generateSmart(Request $request, GrowthContentService $content): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'count' => 'nullable|integer|min:1|max:10',
            'platform' => 'nullable|string|in:facebook,instagram,linkedin,tiktok,twitter',
            'topic' => 'nullable|string|max:500',
        ]);

        try {
            $result = $content->generateFromWinners($company, $validated);

            return response()->json([
                'success' => true,
                'posts' => $result->posts,
                'aiGenerated' => $result->aiGenerated,
                'aiError' => $result->aiError,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function portfolioInsights(Request $request, CrossBrandLearningService $learning, GrowthBenchmarkService $benchmark): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $industry = $company->industry ?? 'other';
        $tips = PortfolioRecommendation::query()
            ->where('approved_for_tenants', true)
            ->where(function ($q) use ($industry) {
                $q->whereNull('industry_cluster')
                    ->orWhere('industry_cluster', $industry)
                    ->orWhere('industry_cluster', 'other');
            })
            ->orderByDesc('confidence_score')
            ->limit(10)
            ->get()
            ->map(fn ($r) => $learning->format($r));

        return response()->json([
            'tips' => $tips->values()->all(),
            'benchmark' => $benchmark->leadToOrderBenchmark($company),
        ]);
    }

    public function predictionAccuracy(Request $request, GrowthPredictionExplainService $explain): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return response()->json($explain->accuracyReport((int) $companyId));
    }

    public function generateVariants(Request $request, GrowthContentService $content): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'count' => 'nullable|integer|min:2|max:5',
            'platform' => 'nullable|string|in:facebook,instagram,linkedin,tiktok,twitter',
            'topic' => 'nullable|string|max:500',
            'saveIndexes' => 'nullable|array',
            'saveIndexes.*' => 'integer|min:0',
        ]);

        try {
            $generated = $content->generateVariants($company, $validated);
            $variants = $generated['variants'];

            if (! empty($validated['saveIndexes'])) {
                $toSave = collect($variants)->filter(fn ($_, $i) => in_array($i, $validated['saveIndexes'], true))->values()->all();
                $saved = $content->saveSelectedVariants($company, $validated['platform'] ?? 'facebook', $toSave);

                return response()->json([
                    'success' => true,
                    'variants' => $variants,
                    'savedPosts' => $saved,
                    'aiGenerated' => $generated['aiGenerated'],
                    'aiError' => $generated['aiError'],
                ]);
            }

            return response()->json([
                'success' => true,
                'variants' => $variants,
                'aiGenerated' => $generated['aiGenerated'],
                'aiError' => $generated['aiError'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function exportAttribution(Request $request, GrowthAttributionExportService $export)
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return $export->csvResponse($company, $request->input('period', '30d'));
    }

    public function applyPattern(Request $request, GrowthLearningPattern $pattern, GrowthPatternService $patterns): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        try {
            $updated = $patterns->applyPattern($pattern, $company);

            return response()->json(['success' => true, 'pattern' => $patterns->format($updated)]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }
}
