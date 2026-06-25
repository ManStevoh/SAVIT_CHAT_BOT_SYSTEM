---
title: Queues & Scheduler
parent: Technical Documentation
nav_order: 13
---

# Queues & Scheduler

Background processing is essential for WhatsApp replies, Growth Engine publishing, and scheduled maintenance.

## Queue configuration

Default: `QUEUE_CONNECTION=database`

Jobs stored in `jobs` table, processed by `php artisan queue:work`.

### Alternatives

| Driver | Use case |
|--------|----------|
| `sync` | Dev/small servers — runs inline, no worker needed |
| `database` | Default — works on cPanel without Redis |
| `redis` | High volume — requires Redis server |

### Running the worker

```bash
# Development
php artisan queue:work

# Production (Supervisor)
php artisan queue:work --sleep=3 --tries=3 --max-time=3600

# Listen mode (dev, auto-reload)
php artisan queue:listen
```

### Failed jobs

```bash
php artisan queue:failed          # List failed
php artisan queue:retry {id}      # Retry one
php artisan queue:retry all       # Retry all
php artisan queue:flush           # Delete all failed
```

Check `failed_jobs` table and `storage/logs/laravel.log`.

## Critical jobs

### ProcessIncomingWhatsAppMessage

| Property | Value |
|----------|-------|
| Trigger | WhatsApp webhook POST |
| Purpose | Generate and send bot reply |
| Failure impact | Customer gets no reply |
| Retry | 3 tries default |

**Most common production issue:** queue worker not running.

### Growth Engine jobs

| Job | Schedule |
|-----|----------|
| `PublishScheduledPostsJob` | Every 5 minutes |
| `SyncMetaMetricsJob` | Daily 06:00 |
| `SyncMetaAdSpendJob` | Daily 06:30 |
| `ProcessCrmFollowUpsJob` | Hourly |
| `ExtractGrowthPatternsJob` | Weekly Mon 08:00 |
| `GenerateWeeklyBriefJob` | Weekly Mon 08:30 |
| `ScorePostPerformanceJob` | Daily 07:00 |
| `GeneratePortfolioRecommendationsJob` | Weekly Mon 07:00 |
| `PrunePortfolioRecommendationsJob` | Weekly Sun 03:00 |
| `SyncGrowthIntegrationsJob` | Daily 05:00 |

## Scheduler

Defined in `LARAVEL_BACKEND/bootstrap/app.php`:

```php
$schedule->command('subscription:expiry-reminders')->dailyAt('09:00');
$schedule->job(new PublishScheduledPostsJob)->everyFiveMinutes();
// ... additional Growth jobs
```

### Cron entry (required)

```cron
* * * * * cd /path/to/LARAVEL_BACKEND && php artisan schedule:run >> /dev/null 2>&1
```

Without cron, scheduled posts won't publish and subscription reminders won't send.

### Verify scheduler

```bash
php artisan schedule:list
php artisan schedule:run --verbose   # Manual run
```

## Artisan commands

| Command | Schedule | Purpose |
|---------|----------|---------|
| `subscription:expiry-reminders` | Daily 09:00 | Email before subscription expires |
| `growth:health` | Manual | Diagnostic checks |
| `growth:scheduler-install` | Manual | Helper to install cron |
| `growth:sync-meta` | Manual | Force Meta metrics sync |
| `growth:pilot-company` | Manual | Enable Growth pilot for company |

## Dev all-in-one

```bash
cd LARAVEL_BACKEND
composer dev
```

Runs concurrently:
- `php artisan serve`
- `php artisan queue:listen`
- `php artisan pail` (logs)
- `npm run dev` (Vite)

## Monitoring

| Metric | Query / command |
|--------|-----------------|
| Pending jobs | `SELECT COUNT(*) FROM jobs` |
| Failed jobs | `SELECT COUNT(*) FROM failed_jobs` |
| Last schedule run | Check cron logs or add logging |
| Worker alive | `supervisorctl status essem-queue` |

Admin system health endpoint reports queue status.

## Performance tuning

| Setting | Recommendation |
|---------|----------------|
| `numprocs` | 2+ workers for high message volume |
| `--sleep=3` | Reduce CPU when queue empty |
| `--max-time=3600` | Restart worker hourly (memory leak prevention) |
| Redis queue | Switch at >1000 messages/day |

## Job dispatch from API

Some endpoints queue work explicitly:

- WhatsApp webhook → `ProcessIncomingWhatsAppMessage`
- `POST .../growth/intelligence/patterns/queue` → pattern extraction
- `POST .../admin/growth-portfolio/queue` → portfolio generation

Synchronous endpoints run AI generation inline (may timeout on slow OpenAI responses — consider queueing for large batches).
