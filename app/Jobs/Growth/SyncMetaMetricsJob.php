<?php

namespace App\Jobs\Growth;

use App\Models\SocialPost;
use App\Services\Growth\MetaInsightsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMetaMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $companyId = null) {}

    public function handle(MetaInsightsService $insights): void
    {
        if ($this->companyId) {
            $insights->syncAllForCompany($this->companyId);
            ScorePostPerformanceJob::dispatch(companyId: $this->companyId);

            return;
        }

        $companyIds = SocialPost::where('status', 'published')
            ->whereNotNull('external_post_id')
            ->distinct()
            ->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $insights->syncAllForCompany((int) $companyId);
            ScorePostPerformanceJob::dispatch(companyId: (int) $companyId);
        }
    }
}
