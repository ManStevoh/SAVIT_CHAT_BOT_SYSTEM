<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GrowthSchedulerInstallCommand extends Command
{
    protected $signature = 'growth:scheduler-install {--show : Print cron line only}';

    protected $description = 'Print production cron instructions for Growth Engine scheduled tasks';

    public function handle(): int
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $cronLine = "* * * * * cd ".base_path()." && {$php} {$artisan} schedule:run >> /dev/null 2>&1";

        $this->line('');
        $this->info('RelayIQ Growth Engine — production scheduler');
        $this->line('');
        $this->line('Add this single cron entry on your server (runs Laravel scheduler every minute):');
        $this->line('');
        $this->comment($cronLine);
        $this->line('');
        $this->line('Scheduled jobs include:');
        $this->line('  • PublishScheduledPostsJob — every 5 minutes');
        $this->line('  • SyncMetaMetricsJob — daily 06:00');
        $this->line('  • SyncMetaAdSpendJob — daily 06:30');
        $this->line('  • ProcessCrmFollowUpsJob — hourly');
        $this->line('  • GeneratePortfolioRecommendationsJob — weekly Mon 07:00');
        $this->line('  • PrunePortfolioRecommendationsJob — weekly Sun 03:00');
        $this->line('  • SyncGrowthIntegrationsJob — daily 05:00');
        $this->line('  • ScorePostPerformanceJob — daily 07:00');
        $this->line('  • ExtractGrowthPatternsJob — weekly Mon 08:00');
        $this->line('  • GenerateWeeklyBriefJob — weekly Mon 08:30');
        $this->line('  • subscription:expiry-reminders — daily 09:00');
        $this->line('');
        $this->line('Ensure QUEUE_CONNECTION=database (or redis) and run a queue worker:');
        $this->comment("{$php} {$artisan} queue:work --tries=3");
        $this->line('');
        $this->line('Verify setup:');
        $this->comment("{$php} {$artisan} growth:health");
        $this->line('');

        if ($this->option('show')) {
            return self::SUCCESS;
        }

        $this->warn('On Windows Task Scheduler, create a task that runs every minute:');
        $this->comment("{$php} {$artisan} schedule:run");

        return self::SUCCESS;
    }
}
