<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        $middleware->statefulApi();
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'api.key' => \App\Http\Middleware\AuthenticateApiKey::class,
            'subscription.active' => \App\Http\Middleware\EnsureSubscriptionActive::class,
            'user.active' => \App\Http\Middleware\EnsureUserActive::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('subscription:expiry-reminders')->dailyAt('09:00');
        $schedule->job(new \App\Jobs\Growth\PublishScheduledPostsJob)->everyFiveMinutes();
        $schedule->job(new \App\Jobs\Growth\SyncMetaMetricsJob)->dailyAt('06:00');
        $schedule->job(new \App\Jobs\Growth\SyncMetaAdSpendJob)->dailyAt('06:30');
        $schedule->job(new \App\Jobs\Growth\ProcessCrmFollowUpsJob)->hourly();
        $schedule->job(new \App\Jobs\Agent\ProcessAgentProactiveEventsJob)->hourly();
        $schedule->job(new \App\Jobs\Agent\GenerateDailyCommerceBriefJob)->dailyAt('07:00');
        $schedule->job(new \App\Jobs\Agent\RunConsciousnessSenseCycleJob)->everyFiveMinutes();
        $schedule->job(new \App\Jobs\Agent\RunBackgroundThinkingJob)->hourly();
        $schedule->job(new \App\Jobs\Agent\CrossBusinessLearningJob)->dailyAt('04:30');
        $schedule->job(new \App\Jobs\Agent\EvaluateCommerceExperimentsJob)->dailyAt('05:30');
        $schedule->job(new \App\Jobs\Platform\DispatchDomainEventsJob)->everyMinute();
        $schedule->job(new \App\Jobs\Platform\ProcessTrialTransitionsJob)->dailyAt('02:00');
        $schedule->job(new \App\Jobs\Growth\GeneratePortfolioRecommendationsJob)->weeklyOn(1, '07:00');
        $schedule->job(new \App\Jobs\Growth\PrunePortfolioRecommendationsJob)->weeklyOn(0, '03:00');
        $schedule->job(new \App\Jobs\Growth\SyncGrowthIntegrationsJob)->dailyAt('05:00');
        $schedule->job(new \App\Jobs\Growth\ExtractGrowthPatternsJob)->weeklyOn(1, '08:00');
        $schedule->job(new \App\Jobs\Growth\GenerateWeeklyBriefJob)->weeklyOn(1, '08:30');
        $schedule->job(new \App\Jobs\Growth\ScorePostPerformanceJob)->dailyAt('07:00');
        $schedule->command('learning:prune-expired')->dailyAt('02:30');
        $schedule->command('learning:sync-embeddings --missing-only')->weeklyOn(0, '03:30');
        $schedule->command('products:sync-embeddings --missing-only')->weeklyOn(0, '04:00');
        $schedule->command('ai:health-check --notify')->dailyAt('07:30');

        // Shared hosting: set AUTO_MIGRATE=true to apply pending migrations via cron.
        if (config('app.auto_migrate')) {
            $schedule->command('migrate:via-cron')
                ->everyFiveMinutes()
                ->withoutOverlapping(10)
                ->appendOutputTo(storage_path('logs/migrate-cron.log'));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
