<?php

namespace App\Services\Growth;

use App\Models\Company;
use App\Models\GrowthBrandProfile;
use App\Models\GrowthInsight;
use App\Models\GrowthLearningPattern;

class GrowthStrategyService
{
    public function __construct(
        protected GrowthAnalyticsService $analytics,
        protected GrowthPatternService $patterns
    ) {}

    /**
     * Weekly content mix plan based on learned patterns.
     *
     * @return array{weekOf: string, totalPosts: int, mix: array<int, array{tag: string, count: int, reason: string}>, platform: ?string, adjustments: array<int, string>}
     */
    public function buildContentMixPlan(Company $company): array
    {
        $profile = GrowthBrandProfile::where('company_id', $company->id)->first();
        $weights = $profile?->content_mix_weights ?? [];
        $intelligence = $this->analytics->contentIntelligence($company->id);
        $totalPosts = 7;

        if (empty($weights)) {
            return [
                'weekOf' => now()->startOfWeek()->toDateString(),
                'totalPosts' => $totalPosts,
                'mix' => [
                    ['tag' => 'product_showcase', 'count' => 3, 'reason' => 'Default mix — publish and gather data'],
                    ['tag' => 'promo', 'count' => 2, 'reason' => 'Drive conversions'],
                    ['tag' => 'testimonial', 'count' => 2, 'reason' => 'Build trust'],
                ],
                'platform' => $intelligence['bestPlatform'] ?? 'facebook',
                'adjustments' => ['Insufficient history — using balanced starter mix'],
            ];
        }

        arsort($weights);
        $topTags = array_slice(array_keys($weights), 0, 4);
        $mix = [];
        $remaining = $totalPosts;
        $adjustments = [];

        foreach ($topTags as $i => $tag) {
            $weight = (float) ($weights[$tag] ?? 1);
            if ($weight < 0.8) {
                $adjustments[] = "Reduce {$tag} content by ~20% (underperforming)";
                continue;
            }
            $count = $i === count($topTags) - 1
                ? $remaining
                : max(1, (int) round($totalPosts * ($weight / array_sum($weights))));
            $count = min($count, $remaining);
            $remaining -= $count;
            $mix[] = [
                'tag' => $tag,
                'count' => $count,
                'reason' => $weight >= 1.3
                    ? 'High converter — increase volume'
                    : 'Maintain presence',
            ];
        }

        if ($remaining > 0 && ! empty($mix)) {
            $mix[0]['count'] += $remaining;
        }

        foreach ($weights as $tag => $weight) {
            if ($weight < 0.7 && ! in_array($tag, array_column($mix, 'tag'), true)) {
                $adjustments[] = "Pause or reduce low-performing \"{$tag}\" content";
            }
        }

        return [
            'weekOf' => now()->startOfWeek()->toDateString(),
            'totalPosts' => $totalPosts,
            'mix' => $mix,
            'platform' => $intelligence['bestPlatform'] ?? 'facebook',
            'adjustments' => $adjustments ?: ['Continue current winning mix'],
        ];
    }

    public function generateWeeklyBrief(Company $company): GrowthInsight
    {
        $summary = $this->analytics->executiveSummary($company->id, '7d');
        $topPosts = $this->analytics->topPerformingPosts($company->id, '7d', 3);
        $plan = $this->buildContentMixPlan($company);
        $patterns = GrowthLearningPattern::where('company_id', $company->id)
            ->where('source', 'company')
            ->orderByDesc('confidence_score')
            ->limit(2)
            ->get();

        $topPostLine = ! empty($topPosts)
            ? "Top post: \"{$topPosts[0]['title']}\" earned ".number_format($topPosts[0]['revenue'], 0).'.'
            : 'Publish more attributed content to unlock weekly insights.';

        $patternLine = $patterns->first()
            ? $patterns->first()->body
            : 'Run pattern extraction to learn what converts.';

        $mixLine = collect($plan['mix'])
            ->map(fn ($m) => "{$m['count']}× {$m['tag']}")
            ->implode(', ');

        $body = "This week: {$summary['revenue']} revenue from {$summary['orders']} orders across {$summary['clicks']} clicks.\n"
            ."{$topPostLine}\n"
            ."Learning: {$patternLine}\n"
            ."Recommended mix ({$plan['totalPosts']} posts): {$mixLine} on {$plan['platform']}.";

        GrowthInsight::where('company_id', $company->id)
            ->where('insight_type', 'weekly_brief')
            ->where('created_at', '>=', now()->subDays(6))
            ->delete();

        return GrowthInsight::create([
            'company_id' => $company->id,
            'insight_type' => 'weekly_brief',
            'title' => 'Weekly performance brief',
            'body' => $body,
            'confidence_score' => 85,
            'data' => [
                'summary' => $summary,
                'contentMix' => $plan,
                'topPosts' => $topPosts,
            ],
        ]);
    }
}
