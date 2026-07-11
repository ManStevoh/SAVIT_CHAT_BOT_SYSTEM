<?php

namespace App\Jobs\Agent;

use App\Services\Agent\Platform\CrossBusinessLearningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CrossBusinessLearningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CrossBusinessLearningService $learning): void
    {
        if (! config('agent.learning.cross_business_enabled', true)) {
            return;
        }

        $learning->analyzeAndRecord();
    }
}
