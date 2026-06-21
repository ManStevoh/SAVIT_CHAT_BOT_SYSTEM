<?php

namespace App\Jobs\Growth;

use App\Models\Company;
use App\Models\SocialPost;
use App\Services\Growth\PostPerformanceScorer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScorePostPerformanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $postId = null, public ?int $companyId = null) {}

    public function handle(PostPerformanceScorer $scorer): void
    {
        if ($this->postId) {
            $post = SocialPost::with('latestMetrics')->find($this->postId);
            if ($post) {
                $scorer->scorePost($post);
            }

            return;
        }

        if ($this->companyId) {
            $scorer->scoreCompanyPosts($this->companyId);

            return;
        }

        Company::where('status', 'active')->pluck('id')->each(function (int $companyId) use ($scorer) {
            $scorer->scoreCompanyPosts($companyId);
        });
    }
}
