<?php

namespace App\Console\Commands;

use App\Services\AI\AiObservabilityService;
use Illuminate\Console\Command;

class AiHealthCheck extends Command
{
    protected $signature = 'ai:health-check {--notify : Record platform alerts for warnings/critical issues}';

    protected $description = 'Check AI embedding coverage, BYOK failures, and queue health';

    public function handle(AiObservabilityService $observability): int
    {
        $alerts = $observability->check();
        if ($alerts === []) {
            $this->info('All AI health checks passed.');

            return self::SUCCESS;
        }

        foreach ($alerts as $alert) {
            $this->line("[{$alert['level']}] {$alert['code']}: {$alert['message']}");
        }

        if ($this->option('notify')) {
            $observability->notifyAdminsIfNeeded();
            $this->info('Alerts logged to application log.');
        }

        return self::SUCCESS;
    }
}
