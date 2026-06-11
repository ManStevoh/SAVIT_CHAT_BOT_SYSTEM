<?php

namespace App\Services\Growth;

use App\Models\AttributionEvent;
use App\Models\SocialPost;
use Illuminate\Support\Str;

class PostPerformanceScorer
{
    public function scorePost(SocialPost $post, int $periodDays = 30): float
    {
        $since = now()->subDays($periodDays);

        $clicks = AttributionEvent::where('social_post_id', $post->id)
            ->where('event_type', 'click')
            ->where('created_at', '>=', $since)
            ->count();
        $leads = AttributionEvent::where('social_post_id', $post->id)
            ->where('event_type', 'lead')
            ->where('created_at', '>=', $since)
            ->count();
        $orders = AttributionEvent::where('social_post_id', $post->id)
            ->where('event_type', 'order')
            ->where('created_at', '>=', $since)
            ->count();
        $revenue = (float) AttributionEvent::where('social_post_id', $post->id)
            ->where('event_type', 'revenue')
            ->where('created_at', '>=', $since)
            ->sum('revenue');

        $reach = (int) ($post->latestMetrics?->reach ?? 0);
        $clickRate = $reach > 0 ? ($clicks / $reach) * 100 : ($clicks > 0 ? 5.0 : 0);
        $leadRate = $clicks > 0 ? ($leads / $clicks) * 100 : 0;
        $orderRate = $leads > 0 ? ($orders / $leads) * 100 : 0;

        $revenueComponent = min(50, $revenue / 1000);
        $conversionComponent = min(30, ($clickRate * 0.15) + ($leadRate * 0.1) + ($orderRate * 0.2));
        $engagementComponent = min(20, (float) ($post->latestMetrics?->engagement_rate ?? 0));

        $score = round($revenueComponent + $conversionComponent + $engagementComponent, 2);
        $score = min(100, max(0, $score));

        $post->update(['performance_score' => $score]);

        if (empty($post->content_tags)) {
            $post->update(['content_tags' => ContentTagger::inferTags($post->content, $post->content_type)]);
        }

        return $score;
    }

    public function scoreCompanyPosts(int $companyId, int $periodDays = 30): int
    {
        $posts = SocialPost::where('company_id', $companyId)
            ->whereIn('status', ['published', 'scheduled'])
            ->with('latestMetrics')
            ->get();

        foreach ($posts as $post) {
            $this->scorePost($post, $periodDays);
        }

        return $posts->count();
    }
}
