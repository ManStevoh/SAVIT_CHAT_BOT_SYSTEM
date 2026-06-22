<?php

namespace App\Jobs\Growth;

use App\Models\SocialPost;
use App\Services\Growth\MetaSocialService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishScheduledPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MetaSocialService $metaSocial): void
    {
        $posts = SocialPost::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->whereNotNull('approved_at')
            ->limit(50)
            ->get();

        foreach ($posts as $post) {
            $metaSocial->publishPost($post);
            SyncSocialPostMetricsJob::dispatch($post->id);
        }
    }
}
