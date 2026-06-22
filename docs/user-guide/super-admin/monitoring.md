---
title: Monitoring
parent: Super Admin
nav_order: 48
---

# Super Admin: Monitoring

System observability for platform health, debugging, and compliance.

## System logs

**URL:** `/admin/logs`

Audit trail of platform events:

| Event type | Examples |
|------------|----------|
| Auth | Login, logout, failed attempts |
| Admin actions | Company suspend, impersonation, settings change |
| Webhooks | WhatsApp, Stripe, M-Pesa received |
| Errors | API failures, job failures |
| Subscription | Created, renewed, cancelled |

Filter by level, company, date range, or event type.

## AI usage

**URL:** `/admin/ai-usage`

OpenAI consumption tracking:

| Column | Description |
|--------|-------------|
| Company | Tenant |
| Period | Date range |
| Requests | API call count |
| Tokens | Prompt + completion tokens |
| Estimated cost | Based on model pricing |

Use to:

- Identify heavy users approaching limits
- Detect abuse or runaway loops
- Plan OpenAI billing budget

## System health

**URL:** `/admin` (health widget) or `GET /api/admin/system-health`

| Check | Healthy | Unhealthy indicator |
|-------|---------|---------------------|
| Database | Connected | Connection error |
| Queue | Worker processing | Growing jobs table, failed_jobs |
| Scheduler | Recent schedule run | Stale timestamps |
| Storage | Writable | Permission errors |
| Growth jobs | Last sync within 24h | Overdue sync |

### Health CLI

```bash
php artisan growth:health
```

Runs Growth Engine diagnostic checks.

## Failed jobs

Check `failed_jobs` table or Laravel Horizon (if installed):

```bash
php artisan queue:failed
php artisan queue:retry all
```

Common failures:

- WhatsApp send API token expired
- OpenAI rate limit
- Meta OAuth token refresh failed

## Laravel logs

Server file: `storage/logs/laravel.log`

Key log patterns:

- `WhatsApp webhook: unknown phone_number_id`
- `signature verification failed`
- `ProcessIncomingWhatsAppMessage`
- Stripe/M-Pesa webhook processing

## Uptime monitoring

External monitor recommended on:

- `GET /up` — Laravel health endpoint
- `GET /api/plans` — API availability
- Landing page `/` — Inertia React UI loads

## Alerting recommendations

| Alert | Threshold |
|-------|-----------|
| Queue depth | > 100 jobs for 10 min |
| Failed jobs | Any new failed job |
| Webhook 403 | Signature failures spike |
| Disk space | < 20% free on server |

## Impersonation audit

All impersonation sessions should appear in system logs. Review periodically for compliance.
