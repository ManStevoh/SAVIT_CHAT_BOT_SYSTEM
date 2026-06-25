<?php

namespace App\Services\Growth;

use App\Models\Company;
use App\Models\GrowthBrandProfile;

class GrowthOptimizerService
{
    public function __construct(
        protected GrowthStrategyService $strategy,
        protected GrowthContentService $content
    ) {}

    /**
     * Generate drafts aligned with the weekly content mix plan.
     *
     * @return array{plan: array<string, mixed>, posts: array<int, array<string, mixed>>}
     */
    public function executeMixPlan(Company $company, ?array $plan = null): array
    {
        if (! GrowthLimitService::isGrowthEnabled($company)) {
            throw new \RuntimeException('Growth Engine is not enabled for your plan.');
        }

        $plan = $plan ?? $this->strategy->buildContentMixPlan($company);
        $platform = $plan['platform'] ?? 'facebook';
        $allPosts = [];

        foreach ($plan['mix'] as $item) {
            $count = (int) ($item['count'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            if (! GrowthLimitService::canGenerateAiContent($company)) {
                break;
            }

            $tag = (string) ($item['tag'] ?? 'general');
            $remaining = GrowthLimitService::getAiPostsLimit($company) - GrowthLimitService::aiPostsUsedThisMonth($company);
            $count = min($count, max(0, $remaining));
            if ($count <= 0) {
                break;
            }

            $result = $this->content->generatePosts($company, [
                'count' => $count,
                'platform' => $platform,
                'topic' => str_replace('_', ' ', $tag).' content for WhatsApp conversions',
                'tone' => $this->toneForTag($tag),
            ]);

            $allPosts = array_merge($allPosts, $result->posts);
        }

        if (empty($allPosts) && GrowthLimitService::canGenerateAiContent($company)) {
            $winnerResult = $this->content->generateFromWinners($company, [
                'count' => min(3, (int) ($plan['totalPosts'] ?? 3)),
                'platform' => $platform,
            ]);
            $allPosts = $winnerResult->posts;
        }

        return ['plan' => $plan, 'posts' => $allPosts];
    }

    /**
     * @return array<string, mixed>
     */
    public function intelligenceSummary(Company $company): array
    {
        $profile = GrowthBrandProfile::where('company_id', $company->id)->first();
        $plan = $this->strategy->buildContentMixPlan($company);

        $latestBrief = \App\Models\GrowthInsight::where('company_id', $company->id)
            ->where('insight_type', 'weekly_brief')
            ->orderByDesc('created_at')
            ->first();

        $patternCount = \App\Models\GrowthLearningPattern::where('company_id', $company->id)
            ->where('is_applied', false)
            ->count();

        return [
            'hasLearningProfile' => $profile !== null,
            'lastLearnedAt' => $profile?->last_learned_at?->toIso8601String(),
            'winningTags' => $profile?->winning_patterns['topTags'] ?? [],
            'contentMix' => $plan,
            'pendingPatterns' => $patternCount,
            'weeklyBrief' => $latestBrief ? [
                'id' => (string) $latestBrief->id,
                'title' => $latestBrief->title,
                'body' => $latestBrief->body,
                'createdAt' => $latestBrief->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    protected function toneForTag(string $tag): string
    {
        return match ($tag) {
            'testimonial' => 'authentic testimonial and social proof',
            'promo' => 'urgent promotional with clear offer',
            'product_showcase' => 'product-focused showcase',
            'educational' => 'helpful educational',
            'urgency' => 'urgent scarcity-driven',
            default => 'conversion-focused friendly',
        };
    }
}
