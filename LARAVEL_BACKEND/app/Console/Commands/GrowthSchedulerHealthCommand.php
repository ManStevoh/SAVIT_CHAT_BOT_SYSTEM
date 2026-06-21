<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GrowthSchedulerHealthCommand extends Command
{
    protected $signature = 'growth:health';

    protected $description = 'Check Growth Engine scheduler, queue, and integration readiness';

    public function handle(): int
    {
        $this->info('Growth Engine health check');
        $this->line('');

        $checks = [
            'Queue connection' => config('queue.default') !== 'sync',
            'Database reachable' => $this->databaseOk(),
            'Meta OAuth configured' => (bool) (config('growth.oauth.meta.client_id') ?: config('whatsapp.embedded_signup_app_id')),
            'GA4 integration' => (bool) config('growth.integrations.ga4.enabled'),
            'Email integration' => (bool) config('growth.integrations.email.enabled'),
        ];

        foreach ($checks as $label => $ok) {
            $this->line(sprintf('  [%s] %s', $ok ? 'OK' : '--', $label));
        }

        $this->line('');
        $this->info('Scheduled Growth jobs (via schedule:run every minute):');
        $jobs = [
            'PublishScheduledPostsJob — every 5 minutes',
            'SyncMetaMetricsJob — daily 06:00',
            'SyncMetaAdSpendJob — daily 06:30',
            'ProcessCrmFollowUpsJob — hourly',
            'GeneratePortfolioRecommendationsJob — weekly Mon 07:00',
            'PrunePortfolioRecommendationsJob — weekly Sun 03:00',
            'SyncGrowthIntegrationsJob — daily 05:00',
            'ScorePostPerformanceJob — daily 07:00',
            'ExtractGrowthPatternsJob — weekly Mon 08:00',
            'GenerateWeeklyBriefJob — weekly Mon 08:30',
        ];
        foreach ($jobs as $job) {
            $this->line("  • {$job}");
        }

        $this->line('');
        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        $this->line("Queue: {$pending} pending, {$failed} failed");

        if ($pending > 100) {
            $this->warn('High queue backlog — ensure queue:work is running.');
        }

        $this->line('');
        $this->comment('Run: php artisan growth:scheduler-install');

        return self::SUCCESS;
    }

    protected function databaseOk(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
