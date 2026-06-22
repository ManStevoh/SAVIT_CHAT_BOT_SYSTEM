<?php

namespace App\Services\Growth;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostMetric;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaInsightsService
{
    public function syncPost(SocialPost $post): ?SocialPostMetric
    {
        if (! $post->external_post_id) {
            return null;
        }

        $account = $this->resolveAccount($post);
        if (! $account?->access_token) {
            return null;
        }

        $graphUrl = rtrim(config('growth.meta.graph_url'), '/');
        $token = $account->access_token;

        try {
            $insightsResp = Http::withToken($token)
                ->timeout(25)
                ->get("{$graphUrl}/{$post->external_post_id}/insights", [
                    'metric' => 'post_impressions,post_impressions_unique,post_engaged_users,post_clicks',
                ]);

            $postResp = Http::withToken($token)
                ->timeout(20)
                ->get("{$graphUrl}/{$post->external_post_id}", [
                    'fields' => 'shares,comments.summary(true),reactions.summary(true)',
                ]);

            $metrics = $this->parseInsights($insightsResp->json('data', []));
            $engagement = $this->parseEngagement($postResp->json());

            $reach = $metrics['post_impressions_unique'] ?: $metrics['post_impressions'];
            $clicks = max($metrics['post_clicks'], $post->attributionLink?->click_count ?? 0);
            $likes = $engagement['reactions'];
            $comments = $engagement['comments'];
            $shares = $engagement['shares'];
            $engaged = $metrics['post_engaged_users'] ?: ($likes + $comments + $shares);
            $engagementRate = $reach > 0 ? round(($engaged / $reach) * 100, 4) : 0;

            return SocialPostMetric::create([
                'social_post_id' => $post->id,
                'recorded_at' => now(),
                'reach' => $reach,
                'impressions' => $metrics['post_impressions'],
                'likes' => $likes,
                'comments' => $comments,
                'shares' => $shares,
                'clicks' => $clicks,
                'engagement_rate' => $engagementRate,
                'raw_data' => [
                    'source' => 'meta_graph_api',
                    'insights' => $insightsResp->json(),
                    'post' => $postResp->json(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('MetaInsightsService sync failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    public function syncAllForCompany(int $companyId): int
    {
        $posts = SocialPost::where('company_id', $companyId)
            ->where('status', 'published')
            ->whereNotNull('external_post_id')
            ->get();

        $count = 0;
        foreach ($posts as $post) {
            if ($this->syncPost($post)) {
                $count++;
            }
        }

        return $count;
    }

    protected function resolveAccount(SocialPost $post): ?SocialAccount
    {
        if ($post->social_account_id) {
            return $post->socialAccount;
        }

        return SocialAccount::where('company_id', $post->company_id)
            ->whereIn('platform', ['facebook', 'instagram'])
            ->where('status', 'connected')
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     * @return array<string, int>
     */
    protected function parseInsights(array $data): array
    {
        $out = [
            'post_impressions' => 0,
            'post_impressions_unique' => 0,
            'post_engaged_users' => 0,
            'post_clicks' => 0,
        ];

        foreach ($data as $metric) {
            $name = $metric['name'] ?? '';
            if (isset($out[$name])) {
                $out[$name] = (int) ($metric['values'][0]['value'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $post
     * @return array{reactions: int, comments: int, shares: int}
     */
    protected function parseEngagement(?array $post): array
    {
        if (! $post) {
            return ['reactions' => 0, 'comments' => 0, 'shares' => 0];
        }

        return [
            'reactions' => (int) ($post['reactions']['summary']['total_count'] ?? 0),
            'comments' => (int) ($post['comments']['summary']['total_count'] ?? 0),
            'shares' => (int) ($post['shares']['count'] ?? 0),
        ];
    }
}
