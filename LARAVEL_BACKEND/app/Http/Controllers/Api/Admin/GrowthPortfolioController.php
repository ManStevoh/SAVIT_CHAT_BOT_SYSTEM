<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Growth\GeneratePortfolioRecommendationsJob;
use App\Models\AttributionEvent;
use App\Models\Company;
use App\Models\PortfolioRecommendation;
use App\Models\SocialPost;
use App\Services\Growth\CrossBrandLearningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GrowthPortfolioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->input('period', '30d');
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
        $since = now()->subDays($days);

        $companies = Company::where('status', 'active')->get();

        $portfolio = $companies->map(function (Company $company) use ($since) {
            $revenue = (float) AttributionEvent::where('company_id', $company->id)
                ->where('event_type', 'revenue')
                ->where('created_at', '>=', $since)
                ->sum('revenue');
            $leads = AttributionEvent::where('company_id', $company->id)
                ->where('event_type', 'lead')
                ->where('created_at', '>=', $since)
                ->count();
            $posts = SocialPost::where('company_id', $company->id)
                ->where('created_at', '>=', $since)
                ->count();

            return [
                'companyId' => (string) $company->id,
                'companyName' => $company->name,
                'leads' => $leads,
                'revenue' => $revenue,
                'posts' => $posts,
            ];
        })->sortByDesc('revenue')->values();

        $topicInsight = SocialPost::query()
            ->join('attribution_events', 'social_posts.id', '=', 'attribution_events.social_post_id')
            ->where('attribution_events.event_type', 'revenue')
            ->where('attribution_events.created_at', '>=', $since)
            ->select('social_posts.platform', DB::raw('SUM(attribution_events.revenue) as total_revenue'))
            ->groupBy('social_posts.platform')
            ->orderByDesc('total_revenue')
            ->first();

        $recommendations = PortfolioRecommendation::with('company')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($r) => app(CrossBrandLearningService::class)->format($r));

        return response()->json([
            'period' => $period,
            'companies' => $portfolio->all(),
            'crossBrandInsight' => $topicInsight
                ? "Content on {$topicInsight->platform} is generating the highest portfolio revenue this period."
                : 'Connect social accounts and publish attributed content to unlock cross-brand insights.',
            'totals' => [
                'leads' => $portfolio->sum('leads'),
                'revenue' => $portfolio->sum('revenue'),
                'posts' => $portfolio->sum('posts'),
            ],
            'recommendations' => $recommendations->values()->all(),
        ]);
    }

    public function generateRecommendations(CrossBrandLearningService $learning): JsonResponse
    {
        $items = $learning->generate(30);

        return response()->json(['success' => true, 'recommendations' => $items]);
    }

    public function queueRecommendations(): JsonResponse
    {
        GeneratePortfolioRecommendationsJob::dispatch();

        return response()->json(['success' => true, 'message' => 'Portfolio AI analysis queued.']);
    }

    public function markRecommendationRead(PortfolioRecommendation $recommendation): JsonResponse
    {
        $recommendation->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function approveRecommendation(Request $request, PortfolioRecommendation $recommendation): JsonResponse
    {
        $validated = $request->validate([
            'approved' => 'required|boolean',
            'industryCluster' => 'nullable|string|max:64',
        ]);

        $recommendation->update([
            'approved_for_tenants' => (bool) $validated['approved'],
            'industry_cluster' => $validated['industryCluster'] ?? $recommendation->industry_cluster,
        ]);

        return response()->json([
            'success' => true,
            'recommendation' => app(CrossBrandLearningService::class)->format($recommendation->fresh()),
        ]);
    }
}
