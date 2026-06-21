<?php

namespace App\Services\Growth;

use App\Models\AttributionEvent;
use App\Models\Company;
use App\Models\PortfolioRecommendation;
use App\Models\SocialPost;
use Illuminate\Support\Facades\DB;

class CrossBrandLearningService
{
    /**
     * Analyze portfolio-wide patterns and write recommendations per company + global.
     */
    public function generate(int $periodDays = 30): array
    {
        $since = now()->subDays($periodDays);
        $created = [];

        PortfolioRecommendation::where('is_read', false)->delete();

        $byPlatform = SocialPost::query()
            ->join('attribution_events', 'social_posts.id', '=', 'attribution_events.social_post_id')
            ->where('attribution_events.event_type', 'revenue')
            ->where('attribution_events.created_at', '>=', $since)
            ->select('social_posts.platform', DB::raw('SUM(attribution_events.revenue) as revenue'), DB::raw('COUNT(DISTINCT social_posts.company_id) as brands'))
            ->groupBy('social_posts.platform')
            ->orderByDesc('revenue')
            ->get();

        $topPlatform = $byPlatform->first();
        if ($topPlatform) {
            $created[] = PortfolioRecommendation::create([
                'company_id' => null,
                'recommendation_type' => 'portfolio_platform',
                'title' => 'Portfolio-wide platform winner',
                'body' => "{$topPlatform->platform} content generated ".number_format((float) $topPlatform->revenue, 0)." in attributed revenue across {$topPlatform->brands} brand(s) this period. Prioritize this channel portfolio-wide.",
                'confidence_score' => 82,
                'data' => ['platform' => $topPlatform->platform, 'revenue' => (float) $topPlatform->revenue],
            ]);
        }

        $byContentType = SocialPost::query()
            ->join('attribution_events', 'social_posts.id', '=', 'attribution_events.social_post_id')
            ->where('attribution_events.event_type', 'revenue')
            ->where('attribution_events.created_at', '>=', $since)
            ->select('social_posts.content_type', DB::raw('SUM(attribution_events.revenue) as revenue'))
            ->groupBy('social_posts.content_type')
            ->orderByDesc('revenue')
            ->first();

        if ($byContentType) {
            $created[] = PortfolioRecommendation::create([
                'company_id' => null,
                'recommendation_type' => 'portfolio_format',
                'title' => 'Best content format across brands',
                'body' => "{$byContentType->content_type} posts are outperforming other formats portfolio-wide. Shift creative production toward this format.",
                'confidence_score' => 75,
                'data' => ['contentType' => $byContentType->content_type],
            ]);
        }

        $companies = Company::where('status', 'active')->get();
        foreach ($companies as $company) {
            $companyRevenue = (float) AttributionEvent::where('company_id', $company->id)
                ->where('event_type', 'revenue')
                ->where('created_at', '>=', $since)
                ->sum('revenue');

            $portfolioAvg = $companies->count() > 0
                ? (float) AttributionEvent::where('event_type', 'revenue')->where('created_at', '>=', $since)->sum('revenue') / $companies->count()
                : 0;

            if ($portfolioAvg > 0 && $companyRevenue < $portfolioAvg * 0.5 && $topPlatform) {
                $created[] = PortfolioRecommendation::create([
                    'company_id' => $company->id,
                    'recommendation_type' => 'brand_gap',
                    'title' => 'Catch up to portfolio average',
                    'body' => "Your attributed revenue is below the portfolio average. Brands winning on {$topPlatform->platform} are seeing stronger conversion — test similar content and WhatsApp CTAs.",
                    'confidence_score' => 68,
                    'data' => [
                        'companyRevenue' => $companyRevenue,
                        'portfolioAvg' => $portfolioAvg,
                        'suggestedPlatform' => $topPlatform->platform,
                    ],
                ]);
            }

            $topPostRow = AttributionEvent::where('company_id', $company->id)
                ->where('event_type', 'revenue')
                ->where('created_at', '>=', $since)
                ->whereNotNull('social_post_id')
                ->select('social_post_id', DB::raw('SUM(revenue) as total'))
                ->groupBy('social_post_id')
                ->orderByDesc('total')
                ->first();

            $bestPost = $topPostRow
                ? SocialPost::find($topPostRow->social_post_id)
                : null;

            if ($bestPost && (float) ($topPostRow->total ?? 0) > 0) {
                $created[] = PortfolioRecommendation::create([
                    'company_id' => $company->id,
                    'recommendation_type' => 'repeat_winner',
                    'title' => 'Double down on what works',
                    'body' => 'Your top attributed post this period shares a winning pattern. Create 3 more posts with a similar topic, CTA, and posting window.',
                    'confidence_score' => 72,
                    'data' => ['postId' => $bestPost->id, 'platform' => $bestPost->platform],
                ]);
            }
        }

        return collect($created)->map(fn (PortfolioRecommendation $r) => $this->format($r))->all();
    }

    public function format(PortfolioRecommendation $r): array
    {
        return [
            'id' => (string) $r->id,
            'companyId' => $r->company_id ? (string) $r->company_id : null,
            'companyName' => $r->company?->name,
            'recommendationType' => $r->recommendation_type,
            'title' => $r->title,
            'body' => $r->body,
            'confidenceScore' => (float) $r->confidence_score,
            'isRead' => $r->is_read,
            'createdAt' => $r->created_at?->toIso8601String(),
        ];
    }
}
