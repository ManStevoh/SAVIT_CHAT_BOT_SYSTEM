<?php

namespace App\Services\Growth;

use App\Models\Company;
use App\Models\GrowthBrandProfile;
use App\Models\GrowthInsight;
use App\Models\GrowthLearningPattern;
use App\Models\SocialPost;

class GrowthInsightService
{
    public function __construct(
        protected GrowthAnalyticsService $analytics
    ) {}

    public function generateInsights(Company $company): array
    {
        $intelligence = $this->analytics->contentIntelligence($company->id);
        $created = [];

        if ($intelligence['bestPlatform']) {
            $created[] = GrowthInsight::create([
                'company_id' => $company->id,
                'insight_type' => 'strategy',
                'title' => 'Best performing platform',
                'body' => "Posts on {$intelligence['bestPlatform']} are generating the highest attributed revenue. Consider increasing publish frequency on this channel.",
                'confidence_score' => 75,
                'data' => ['platform' => $intelligence['bestPlatform']],
            ]);
        }

        if ($intelligence['bestContentType']) {
            $created[] = GrowthInsight::create([
                'company_id' => $company->id,
                'insight_type' => 'format',
                'title' => 'Top content format',
                'body' => "{$intelligence['bestContentType']} content converts better than other formats for your audience.",
                'confidence_score' => 70,
                'data' => ['contentType' => $intelligence['bestContentType']],
            ]);
        }

        if ($intelligence['bestPostingHour'] !== null) {
            $hour = $intelligence['bestPostingHour'];
            $created[] = GrowthInsight::create([
                'company_id' => $company->id,
                'insight_type' => 'timing',
                'title' => 'Optimal posting time',
                'body' => "Posts published around {$hour}:00 have generated the most revenue. Schedule high-intent content near this window.",
                'confidence_score' => 65,
                'data' => ['hour' => $hour],
            ]);
        }

        $topPost = SocialPost::where('company_id', $company->id)
            ->where('status', 'published')
            ->latest('published_at')
            ->first();

        if ($topPost) {
            $created[] = GrowthInsight::create([
                'company_id' => $company->id,
                'insight_type' => 'topic',
                'title' => 'Content recommendation',
                'body' => 'Create more posts similar to your recent high-performing topics to sustain lead generation.',
                'confidence_score' => 60,
                'data' => ['samplePostId' => $topPost->id],
            ]);
        }

        $profile = GrowthBrandProfile::where('company_id', $company->id)->first();
        if ($profile?->winning_patterns['topTags'] ?? null) {
            $tags = implode(', ', $profile->winning_patterns['topTags']);
            $created[] = GrowthInsight::create([
                'company_id' => $company->id,
                'insight_type' => 'learning',
                'title' => 'Learned content patterns',
                'body' => "Your top converting tags: {$tags}. Use Smart Generate or Execute Mix Plan to apply.",
                'confidence_score' => 80,
                'data' => ['topTags' => $profile->winning_patterns['topTags']],
            ]);
        }

        $pattern = GrowthLearningPattern::where('company_id', $company->id)
            ->where('is_applied', false)
            ->orderByDesc('confidence_score')
            ->first();

        if ($pattern) {
            $created[] = GrowthInsight::create([
                'company_id' => $company->id,
                'insight_type' => 'action',
                'title' => $pattern->title,
                'body' => $pattern->body,
                'confidence_score' => (float) $pattern->confidence_score,
                'data' => ['patternId' => $pattern->id],
            ]);
        }

        return collect($created)->map(fn (GrowthInsight $i) => $this->formatInsight($i))->all();
    }

    public function formatInsight(GrowthInsight $insight): array
    {
        return [
            'id' => (string) $insight->id,
            'insightType' => $insight->insight_type,
            'title' => $insight->title,
            'body' => $insight->body,
            'confidenceScore' => (float) $insight->confidence_score,
            'isRead' => $insight->is_read,
            'createdAt' => $insight->created_at?->toIso8601String(),
        ];
    }
}
