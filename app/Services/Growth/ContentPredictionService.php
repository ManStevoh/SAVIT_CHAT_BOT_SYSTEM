<?php

namespace App\Services\Growth;

use App\Models\AttributionEvent;
use App\Models\GrowthBrandProfile;
use App\Models\SocialPost;
use Illuminate\Support\Str;

class ContentPredictionService
{
    public function __construct(
        protected GrowthAnalyticsService $analytics,
        protected GrowthPredictionExplainService $explain
    ) {}

    public function predictAndStore(SocialPost $post): array
    {
        $prediction = $this->predictDraft($post);
        $post->update([
            'predicted_revenue_score' => $prediction['score'],
            'prediction_factors' => $prediction['factors'],
            'content_tags' => $prediction['tags'],
        ]);

        return $prediction;
    }

    /**
     * @return array{score: float, estimatedRevenue: float, factors: array<string, mixed>, tags: array<int, string>}
     */
    public function predictDraft(SocialPost $post): array
    {
        $companyId = (int) $post->company_id;
        $profile = GrowthBrandProfile::where('company_id', $companyId)->first();
        $intelligence = $this->analytics->contentIntelligence($companyId);
        $tags = ContentTagger::inferTags($post->content, $post->content_type);

        $avgRevenue = $this->averagePostRevenue($companyId);
        $tagMultiplier = $this->tagPerformanceMultiplier($companyId, $tags, $profile);
        $platformMultiplier = $this->platformMultiplier($companyId, $post->platform, $intelligence);
        $contentTypeMultiplier = $this->contentTypeMultiplier($post->content_type, $intelligence);
        $ctaBonus = in_array('whatsapp_cta', $tags, true) ? 1.15 : 0.95;

        $baseScore = 40.0;
        $score = $baseScore * $tagMultiplier * $platformMultiplier * $contentTypeMultiplier * $ctaBonus;
        $score = min(100, max(5, round($score, 2)));

        $hasEnoughData = $this->explain->hasMinimumData($companyId);
        $estimatedRevenue = $hasEnoughData
            ? round($avgRevenue * $tagMultiplier * $platformMultiplier, 2)
            : null;

        $factors = [
            'tagMultiplier' => round($tagMultiplier, 2),
            'platformMultiplier' => round($platformMultiplier, 2),
            'contentTypeMultiplier' => round($contentTypeMultiplier, 2),
            'ctaBonus' => $ctaBonus,
            'avgHistoricalRevenue' => $avgRevenue,
            'estimatedRevenue' => $estimatedRevenue,
            'topTags' => $profile?->winning_patterns['topTags'] ?? [],
            'bestPlatform' => $intelligence['bestPlatform'],
            'hasEnoughData' => $hasEnoughData,
        ];

        return [
            'score' => $score,
            'estimatedRevenue' => $estimatedRevenue,
            'hasEnoughData' => $hasEnoughData,
            'explanations' => $this->explain->explainFactors($factors, $post->platform),
            'factors' => $factors,
            'tags' => $tags,
        ];
    }

    public function scoreCompanyDrafts(int $companyId): array
    {
        $drafts = SocialPost::where('company_id', $companyId)
            ->whereIn('status', ['draft', 'scheduled'])
            ->get();

        return $drafts->map(function (SocialPost $post) {
            $prediction = $this->predictAndStore($post);

            return [
                'postId' => (string) $post->id,
                'title' => $post->title ?? Str::limit($post->content, 50),
                'predictedScore' => $prediction['score'],
                'estimatedRevenue' => $prediction['estimatedRevenue'],
                'hasEnoughData' => $prediction['hasEnoughData'],
                'explanations' => $prediction['explanations'],
                'tags' => $prediction['tags'],
                'factors' => $prediction['factors'],
            ];
        })->sortByDesc('predictedScore')->values()->all();
    }

    protected function averagePostRevenue(int $companyId): float
    {
        $posts = SocialPost::where('company_id', $companyId)->where('status', 'published')->pluck('id');
        if ($posts->isEmpty()) {
            return 500.0;
        }

        $total = (float) AttributionEvent::whereIn('social_post_id', $posts)
            ->where('event_type', 'revenue')
            ->sum('revenue');

        return max(100, $total / max(1, $posts->count()));
    }

    /**
     * @param  array<int, string>  $tags
     */
    protected function tagPerformanceMultiplier(int $companyId, array $tags, ?GrowthBrandProfile $profile): float
    {
        $weights = $profile?->content_mix_weights ?? [];
        if (empty($weights)) {
            return 1.0;
        }

        $multiplier = 1.0;
        foreach ($tags as $tag) {
            if (isset($weights[$tag])) {
                $multiplier *= (float) $weights[$tag];
            }
        }

        return min(2.5, max(0.5, $multiplier));
    }

    protected function platformMultiplier(int $companyId, string $platform, array $intelligence): float
    {
        $best = $intelligence['bestPlatform'] ?? null;
        if (! $best) {
            return 1.0;
        }

        return $platform === $best ? 1.25 : 0.9;
    }

    protected function contentTypeMultiplier(?string $contentType, array $intelligence): float
    {
        $best = $intelligence['bestContentType'] ?? null;
        if (! $best || ! $contentType) {
            return 1.0;
        }

        return $contentType === $best ? 1.15 : 0.95;
    }
}
