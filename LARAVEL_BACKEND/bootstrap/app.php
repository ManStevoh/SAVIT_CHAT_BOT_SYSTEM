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
        ]);
        $middleware->statefulApi();
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'subscription.active' => \App\Http\Middleware\EnsureSubscriptionActive::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('subscription:expiry-reminders')->dailyAt('09:00');
        $schedule->job(new \App\Jobs\Growth\PublishScheduledPostsJob)->everyFiveMinutes();
        $schedule->job(new \App\Jobs\Growth\SyncMetaMetricsJob)->dailyAt('06:00');
        $schedule->job(new \App\Jobs\Growth\SyncMetaAdSpendJob)->dailyAt('06:30');
        $schedule->job(new \App\Jobs\Growth\ProcessCrmFollowUpsJob)->hourly();
        $schedule->job(new \App\Jobs\Growth\GeneratePortfolioRecommendationsJob)->weeklyOn(1, '07:00');
        $schedule->job(new \App\Jobs\Growth\PrunePortfolioRecommendationsJob)->weeklyOn(0, '03:00');
        $schedule->job(new \App\Jobs\Growth\SyncGrowthIntegrationsJob)->dailyAt('05:00');
        $schedule->job(new \App\Jobs\Growth\ExtractGrowthPatternsJob)->weeklyOn(1, '08:00');
        $schedule->job(new \App\Jobs\Growth\GenerateWeeklyBriefJob)->weeklyOn(1, '08:30');
        $schedule->job(new \App\Jobs\Growth\ScorePostPerformanceJob)->dailyAt('07:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
