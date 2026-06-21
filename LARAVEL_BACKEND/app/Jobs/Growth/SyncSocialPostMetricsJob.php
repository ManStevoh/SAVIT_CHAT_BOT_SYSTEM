<?php

namespace App\Jobs\Growth;

use App\Models\SocialPost;
use App\Services\Growth\MetaSocialService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSocialPostMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $postId) {}

    public function handle(MetaSocialService $metaSocial): void
    {
        $post = SocialPost::find($this->postId);
        if ($post) {
            $metaSocial->syncPostMetrics($post);
        }
    }
}
