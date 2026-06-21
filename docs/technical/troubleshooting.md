---
title: Troubleshooting
parent: Technical Documentation
nav_order: 14
---

# Troubleshooting

Common issues and resolution steps.

## WhatsApp: no auto-reply

| # | Check | Fix |
|---|-------|-----|
| 1 | Queue worker running? | `php artisan queue:work` or Supervisor |
| 2 | Auto-reply enabled? | Dashboard → Settings → AI |
| 3 | Active subscription? | Dashboard → Subscription |
| 4 | WhatsApp connected? | Settings → WhatsApp status |
| 5 | Meta webhook subscribed? | Meta App → messages subscription |
| 6 | Callback URL correct? | HTTPS + `/api/whatsapp/webhook` |
| 7 | Verify token matches? | Admin Settings ↔ Meta Configuration |
| 8 | App secret correct? | Mismatch causes 403 on webhook POST |
| 9 | Escalation keyword? | "agent"/"human" skips bot |
| 10 | Jobs failing? | `php artisan queue:failed` |

**Quick test:** Set `QUEUE_CONNECTION=sync` temporarily — if replies work, queue worker is the issue.

## WhatsApp: webhook 403

- Meta App Secret in Admin Settings doesn't match Meta Developer app
- Fix secret or clear it to disable signature check (not recommended production)

## WhatsApp: unknown phone_number_id

- Company's Phone Number ID in dashboard doesn't match Meta
- Reconnect WhatsApp with correct ID from Meta → API Setup

## Frontend: API errors / CORS

| Symptom | Fix |
|---------|-----|
| CORS blocked | Set `FRONTEND_URL` on backend to exact Vercel URL |
| 401 on all requests | Token expired — re-login |
| Network error | Check `NEXT_PUBLIC_API_URL` points to live backend |
| Mock data showing | Set `NEXT_PUBLIC_USE_MOCK_API=false` |

## Frontend: login works locally, fails production

1. Verify Vercel env vars
2. Redeploy after env change
3. Check backend `SANCTUM_STATEFUL_DOMAINS`
4. Confirm backend HTTPS certificate valid

## Backend: Index of / listing

Document root points to Laravel root instead of `public/`. Fix in cPanel or Nginx config.

## Backend: 500 errors

```bash
tail -f storage/logs/laravel.log
php artisan config:clear
php artisan cache:clear
```

Common causes:
- Missing `APP_KEY`
- Database connection failure
- Unrun migrations

## Stripe: subscription not activating

1. Stripe Dashboard → Webhooks → check delivery logs
2. Verify webhook secret in Admin → Payment Gateways
3. Confirm endpoint URL reachable
4. Check `checkout.session.completed` event selected

## M-Pesa: STK not received

1. Verify shortcode and passkey (PayBill vs Till)
2. Check callback URL reachable: `/api/mpesa/callback`
3. Confirm Daraja app credentials (platform or company)
4. Sandbox vs production environment match

## M-Pesa: payment success but order still pending

- Callback received but cache key mismatch
- Check `laravel.log` for callback processing
- Verify same callback URL registered in Daraja portal

## Growth: posts not publishing

1. Cron running? `php artisan schedule:list`
2. Queue worker running?
3. Post status = approved + scheduled time passed?
4. Social account connected with valid OAuth token?
5. Plan limits not exceeded?

```bash
php artisan growth:health
```

## Growth: OAuth fails

- Redirect URI mismatch: must be `{APP_URL}/oauth/growth/callback`
- Register exact URI in platform developer console
- Check client ID/secret env vars

## OpenAI: no AI replies (FAQ works)

- OpenAI key not set in Admin → Settings
- API quota exceeded — check OpenAI dashboard
- Check Admin → AI Usage for errors

## Email not sending

1. Admin → Settings → Test email
2. Verify SMTP credentials
3. Check spam folder
4. Review `laravel.log` for mail errors

## Database migration errors

```bash
php artisan migrate:status
php artisan migrate --force   # Production
```

Backup DB before migrating. For conflicts, review specific migration error.

## High queue backlog

```bash
# Check pending count
php artisan queue:monitor

# Add worker processes in Supervisor
numprocs=2

# Consider Redis queue driver
```

## Subscription expired but customer still messaging

Expected: bot sends unavailable message. If nothing sent, queue worker issue.

## Impersonation stuck

Log out completely. Clear localStorage `auth_token`. Log back in as admin.

## Logs locations

| Log | Path |
|-----|------|
| Laravel | `storage/logs/laravel.log` |
| Queue worker | Supervisor stdout log |
| Web server | Apache/Nginx error logs |
| Admin UI | Admin → Logs (audit events) |

## Health checks

```bash
curl https://your-backend.com/up
curl https://your-backend.com/api/plans
php artisan growth:health
php artisan queue:monitor
```

## Getting help

When reporting issues, include:

1. Timestamp of incident
2. Company ID or phone number ID
3. Relevant `laravel.log` excerpt
4. Queue failed job exception
5. Meta/Stripe webhook delivery ID
