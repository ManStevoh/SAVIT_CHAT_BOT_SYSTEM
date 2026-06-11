<?php

namespace App\Services\Growth;

use App\Models\Company;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostMetric;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaSocialService
{
    public function connectAccount(Company $company, array $data): SocialAccount
    {
        return SocialAccount::updateOrCreate(
            [
                'company_id' => $company->id,
                'platform' => $data['platform'] ?? 'facebook',
                'external_account_id' => $data['external_account_id'] ?? $data['page_id'] ?? null,
            ],
            [
                'account_name' => $data['account_name'] ?? 'Meta Page',
                'page_id' => $data['page_id'] ?? null,
                'access_token' => $data['access_token'] ?? null,
                'status' => 'connected',
                'connected_at' => now(),
                'metadata' => $data['metadata'] ?? null,
            ]
        );
    }

    public function publishPost(SocialPost $post): SocialPost
    {
        $account = $post->socialAccount;
        if (! $account || ! $account->isConnected()) {
            $account = SocialAccount::where('company_id', $post->company_id)
                ->whereIn('platform', ['facebook', 'instagram'])
                ->where('status', 'connected')
                ->first();
        }

        if (! $account || ! $account->access_token || ! $account->page_id) {
            $post->update(['status' => 'published', 'published_at' => now()]);

            return $post->fresh();
        }

        $graphUrl = rtrim(config('growth.meta.graph_url'), '/');
        $message = $post->content;
        if (! empty($post->hashtags)) {
            $message .= "\n\n".implode(' ', array_map(fn ($h) => str_starts_with($h, '#') ? $h : "#{$h}", $post->hashtags));
        }

        try {
            $published = $post->platform === 'instagram'
                ? $this->publishToInstagram($graphUrl, $account, $post, $message)
                : $this->publishToFacebook($graphUrl, $account, $message);

            if ($published['success']) {
                $post->update([
                    'status' => 'published',
                    'published_at' => now(),
                    'external_post_id' => $published['post_id'],
                    'social_account_id' => $account->id,
                    'publish_error' => null,
                ]);
            } else {
                $error = $published['error'] ?? 'Unknown Meta API error';
                Log::warning('Meta publish failed', ['post_id' => $post->id, 'error' => $error]);
                $post->update(['status' => 'failed', 'publish_error' => $error]);
            }
        } catch (\Throwable $e) {
            Log::warning('Meta publish error', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            $post->update(['status' => 'failed', 'publish_error' => $e->getMessage()]);
        }

        return $post->fresh();
    }

    /**
     * @return array{success: bool, post_id?: string, error?: string}
     */
    protected function publishToFacebook(string $graphUrl, SocialAccount $account, string $message): array
    {
        $response = Http::withToken($account->access_token)
            ->timeout(30)
            ->post("{$graphUrl}/{$account->page_id}/feed", ['message' => $message]);

        if ($response->successful()) {
            return ['success' => true, 'post_id' => (string) $response->json('id')];
        }

        return ['success' => false, 'error' => $response->body()];
    }

    /**
     * @return array{success: bool, post_id?: string, error?: string}
     */
    protected function publishToInstagram(string $graphUrl, SocialAccount $account, SocialPost $post, string $message): array
    {
        $igUserId = $account->metadata['instagram_business_account_id'] ?? $account->external_account_id;
        if (! $igUserId) {
            return ['success' => false, 'error' => 'No Instagram Business Account ID on connected account.'];
        }

        $mediaUrls = $post->media_urls ?? [];
        $imageUrl = is_array($mediaUrls) ? ($mediaUrls[0] ?? null) : null;

        if (! $imageUrl) {
            return ['success' => false, 'error' => 'Instagram requires at least one image in media_urls.'];
        }

        $containerResp = Http::withToken($account->access_token)
            ->timeout(30)
            ->post("{$graphUrl}/{$igUserId}/media", [
                'image_url' => $imageUrl,
                'caption' => $message,
            ]);

        if (! $containerResp->successful()) {
            return ['success' => false, 'error' => $containerResp->body()];
        }

        $creationId = $containerResp->json('id');
        $publishResp = Http::withToken($account->access_token)
            ->timeout(30)
            ->post("{$graphUrl}/{$igUserId}/media_publish", [
                'creation_id' => $creationId,
            ]);

        if ($publishResp->successful()) {
            return ['success' => true, 'post_id' => (string) $publishResp->json('id')];
        }

        return ['success' => false, 'error' => $publishResp->body()];
    }

    public function syncPostMetrics(SocialPost $post): ?SocialPostMetric
    {
        $metric = app(MetaInsightsService::class)->syncPost($post);
        if ($metric) {
            return $metric;
        }

        if (! $post->external_post_id) {
            return $this->recordEstimatedMetrics($post);
        }

        return null;
    }

    protected function recordEstimatedMetrics(SocialPost $post): SocialPostMetric
    {
        $clicks = $post->attributionLink?->click_count ?? 0;

        return SocialPostMetric::create([
            'social_post_id' => $post->id,
            'recorded_at' => now(),
            'reach' => max($clicks * 10, 0),
            'impressions' => max($clicks * 12, 0),
            'clicks' => $clicks,
            'engagement_rate' => $clicks > 0 ? 5.0 : 0,
            'raw_data' => ['source' => 'attribution_estimate'],
        ]);
    }
}
