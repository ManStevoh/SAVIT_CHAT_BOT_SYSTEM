<?php

namespace App\Jobs\Agent;

use App\Models\CommerceAgentRun;
use App\Models\Company;
use App\Services\Agent\Specialists\CommerceSpecialistOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunCommerceSpecialistJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $runId) {}

    public function handle(CommerceSpecialistOrchestrator $orchestrator): void
    {
        $run = CommerceAgentRun::find($this->runId);
        if (! $run) {
            return;
        }

        $company = Company::find($run->company_id);
        if (! $company) {
            $run->update(['status' => 'failed', 'completed_at' => now()]);

            return;
        }

        $specialist = $orchestrator->specialistForType($run->agent_type);
        if (! $specialist) {
            $run->update([
                'status' => 'failed',
                'output' => ['error' => 'Unknown specialist type'],
                'completed_at' => now(),
            ]);

            return;
        }

        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $output = $specialist->analyzeBackground($company, $run->input ?? []);
            $run->update([
                'status' => 'completed',
                'output' => $output,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'output' => ['error' => $e->getMessage()],
                'completed_at' => now(),
            ]);
        }
    }
}
