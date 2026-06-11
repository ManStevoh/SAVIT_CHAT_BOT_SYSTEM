<?php

namespace App\Services\Growth;

use App\Models\AttributionEvent;
use App\Models\Company;
use App\Models\GrowthBrandProfile;
use App\Models\GrowthLearningPattern;
use App\Models\PortfolioRecommendation;
use App\Models\SocialPost;
class GrowthPatternService
{
    public function __construct(
        protected GrowthAnalyticsService $analytics,
        protected PostPerformanceScorer $scorer
    ) {}

    public function extractForCompany(Company $company, int $periodDays = 30): array
    {
        $this->scorer->scoreCompanyPosts($company->id, $periodDays);
        $since = now()->subDays($periodDays);
        $created = [];

        GrowthLearningPattern::where('company_id', $company->id)
            ->where('source', 'company')
            ->where('is_applied', false)
            ->delete();

        $tagStats = $this->tagRevenueStats($company->id, $since);
        $topTag = collect($tagStats)->sortByDesc('revenue')->first();
        $avgTagRevenue = collect($tagStats)->avg('revenue') ?: 0;

        if ($topTag && $avgTagRevenue > 0 && $topTag['revenue'] > $avgTagRevenue * 1.3) {
            $lift = round($topTag['revenue'] / max(1, $avgTagRevenue), 1);
            $created[] = $this->storePattern($company->id, 'content_tag', 'company', [
                'title' => ucfirst(str_replace('_', ' ', $topTag['tag'])).' content wins',
                'body' => "Posts tagged \"{$topTag['tag']}\" generated ".number_format($topTag['revenue'], 0)." in revenue — {$lift}× your average. Increase this format by 20% next week.",
                'metrics' => $topTag,
                'confidence' => min(95, 60 + ($lift * 10)),
            ]);
        }

        $intelligence = $this->analytics->contentIntelligence($company->id);
        if ($intelligence['bestPlatform']) {
            $created[] = $this->storePattern($company->id, 'platform', 'company', [
                'title' => 'Best platform: '.$intelligence['bestPlatform'],
                'body' => "Shift 30% more content to {$intelligence['bestPlatform']} where your attributed revenue is highest.",
                'metrics' => ['platform' => $intelligence['bestPlatform']],
                'confidence' => 78,
            ]);
        }

        $topPost = SocialPost::where('company_id', $company->id)
            ->where('status', 'published')
            ->orderByDesc('performance_score')
            ->first();

        if ($topPost && (float) $topPost->performance_score > 50) {
            $created[] = $this->storePattern($company->id, 'winner_post', 'company', [
                'title' => 'Replicate your top post pattern',
                'body' => 'Your highest-scoring post shares a winning structure. Generate 3 more posts using the same tone, CTA, and tags.',
                'metrics' => [
                    'postId' => $topPost->id,
                    'score' => (float) $topPost->performance_score,
                    'tags' => $topPost->content_tags ?? [],
                ],
                'confidence' => 72,
            ]);
        }

        $portfolioPatterns = $this->importPortfolioPatterns($company);
        $created = array_merge($created, $portfolioPatterns);

        $this->updateBrandProfile($company, $tagStats, $intelligence);

        return collect($created)->map(fn (GrowthLearningPattern $p) => $this->format($p))->all();
    }

    public function applyPattern(GrowthLearningPattern $pattern, Company $company): GrowthLearningPattern
    {
        if ($pattern->company_id && (int) $pattern->company_id !== (int) $company->id) {
            throw new \RuntimeException('Pattern does not belong to this company.');
        }

        $pattern->update([
            'is_applied' => true,
            'applied_count' => $pattern->applied_count + 1,
        ]);

        if ($pattern->source === 'portfolio' && ! $pattern->company_id) {
            GrowthLearningPattern::create([
                'company_id' => $company->id,
                'pattern_type' => $pattern->pattern_type,
                'source' => 'portfolio_applied',
                'title' => $pattern->title,
                'body' => $pattern->body,
                'metrics' => $pattern->metrics,
                'confidence_score' => $pattern->confidence_score,
                'is_applied' => true,
            ]);
        }

        return $pattern->fresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function patternsForCompany(int $companyId): array
    {
        return GrowthLearningPattern::where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)
                ->orWhere(function ($q2) {
                    $q2->whereNull('company_id')->where('source', 'portfolio');
                });
        })
            ->orderByDesc('confidence_score')
            ->limit(20)
            ->get()
            ->map(fn (GrowthLearningPattern $p) => $this->format($p))
            ->all();
    }

    protected function tagRevenueStats(int $companyId, $since): array
    {
        $posts = SocialPost::where('company_id', $companyId)
            ->where('status', 'published')
            ->where('created_at', '>=', $since)
            ->get();

        $stats = [];
        foreach ($posts as $post) {
            $revenue = (float) AttributionEvent::where('social_post_id', $post->id)
                ->where('event_type', 'revenue')
                ->sum('revenue');
            $tags = $post->content_tags ?? ContentTagger::inferTags($post->content, $post->content_type);
            foreach ($tags as $tag) {
                if (! isset($stats[$tag])) {
                    $stats[$tag] = ['tag' => $tag, 'posts' => 0, 'revenue' => 0];
                }
                $stats[$tag]['posts']++;
                $stats[$tag]['revenue'] += $revenue;
            }
        }

        return array_values($stats);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function storePattern(?int $companyId, string $type, string $source, array $payload): GrowthLearningPattern
    {
        return GrowthLearningPattern::create([
            'company_id' => $companyId,
            'pattern_type' => $type,
            'source' => $source,
            'title' => $payload['title'],
            'body' => $payload['body'],
            'metrics' => $payload['metrics'] ?? null,
            'confidence_score' => $payload['confidence'] ?? 70,
        ]);
    }

    /**
     * @return array<int, GrowthLearningPattern>
     */
    protected function importPortfolioPatterns(Company $company): array
    {
        $created = [];
        $recs = PortfolioRecommendation::where(function ($q) use ($company) {
            $q->whereNull('company_id')->orWhere('company_id', $company->id);
        })
            ->where('is_read', false)
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        foreach ($recs as $rec) {
            $exists = GrowthLearningPattern::where('company_id', $company->id)
                ->where('source', 'portfolio')
                ->where('title', $rec->title)
                ->exists();
            if ($exists) {
                continue;
            }

            $created[] = GrowthLearningPattern::create([
                'company_id' => $company->id,
                'pattern_type' => 'portfolio_'.$rec->recommendation_type,
                'source' => 'portfolio',
                'title' => $rec->title,
                'body' => $rec->body,
                'metrics' => $rec->data,
                'confidence_score' => $rec->confidence_score,
            ]);
        }

        return $created;
    }

    protected function updateBrandProfile(Company $company, array $tagStats, array $intelligence): void
    {
        $weights = [];
        $avgRevenue = collect($tagStats)->avg('revenue') ?: 1;
        foreach ($tagStats as $stat) {
            $weights[$stat['tag']] = round(min(2.0, max(0.5, $stat['revenue'] / max(1, $avgRevenue))), 2);
        }

        $topTags = collect($tagStats)->sortByDesc('revenue')->take(3)->pluck('tag')->all();

        GrowthBrandProfile::updateOrCreate(
            ['company_id' => $company->id],
            [
                'winning_patterns' => [
                    'topTags' => $topTags,
                    'bestPlatform' => $intelligence['bestPlatform'],
                    'bestContentType' => $intelligence['bestContentType'],
                    'bestPostingHour' => $intelligence['bestPostingHour'],
                ],
                'content_mix_weights' => $weights,
                'last_learned_at' => now(),
            ]
        );
    }

    public function format(GrowthLearningPattern $pattern): array
    {
        return [
            'id' => (string) $pattern->id,
            'companyId' => $pattern->company_id ? (string) $pattern->company_id : null,
            'patternType' => $pattern->pattern_type,
            'source' => $pattern->source,
            'title' => $pattern->title,
            'body' => $pattern->body,
            'metrics' => $pattern->metrics,
            'confidenceScore' => (float) $pattern->confidence_score,
            'isApplied' => $pattern->is_applied,
            'appliedCount' => $pattern->applied_count,
            'createdAt' => $pattern->created_at?->toIso8601String(),
        ];
    }
}
