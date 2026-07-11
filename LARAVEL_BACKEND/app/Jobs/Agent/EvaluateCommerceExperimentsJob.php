<?php

namespace App\Jobs\Agent;

use App\Models\CommerceExperiment;
use App\Services\Agent\Platform\CommerceExperimentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateCommerceExperimentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CommerceExperimentService $experiments): void
    {
        if (! config('agent.experiments.enabled', true)) {
            return;
        }

        $running = CommerceExperiment::where('status', 'running')
            ->where('started_at', '<=', now()->subDays(7))
            ->get();

        foreach ($running as $experiment) {
            $experiments->evaluateWinner($experiment);
        }
    }
}
