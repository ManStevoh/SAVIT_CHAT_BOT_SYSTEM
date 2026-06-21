# Growth Engine — Pilot Deployment Guide

Deploy the Growth Engine for one pilot client (real Facebook/Instagram OAuth + metrics + ads + CRM).

## 1. Server requirements

- PHP 8.2+, Composer, Node.js (for Next.js frontend)
- `QUEUE_CONNECTION=database` (or `redis`)
- Cron: `* * * * * php artisan schedule:run`
- Queue worker: `php artisan queue:work --tries=3`

```bash
php artisan growth:scheduler-install
php artisan growth:health
```

## 2. Environment variables

```env
APP_URL=https://api.yourdomain.com
FRONTEND_URL=https://app.yourdomain.com

GROWTH_META_APP_ID=your_meta_app_id
GROWTH_META_APP_SECRET=your_meta_app_secret
GROWTH_DEFAULT_CURRENCY=KES

GROWTH_CRM_HOURS_QUIET=24
GROWTH_CRM_MAX_FOLLOW_UPS=2
GROWTH_PORTFOLIO_PRUNE_DAYS=90

# Optional Layer 1 integrations
GROWTH_GA4_ENABLED=false
GROWTH_GA4_MEASUREMENT_ID=
GROWTH_GA4_API_SECRET=
GROWTH_EMAIL_SYNC_ENABLED=false
GROWTH_EMAIL_PROVIDER=mailchimp
```

## 3. Meta Developer Console

1. Create or reuse Meta app (same as WhatsApp is fine).
2. Add **Facebook Login** product.
3. Valid OAuth Redirect URI:
   ```
   https://api.yourdomain.com/oauth/growth/callback
   ```
4. Request permissions:
   - `pages_show_list`
   - `pages_read_engagement`
   - `pages_manage_posts`
   - `instagram_basic`
   - `instagram_content_publish`
   - `ads_read`
   - `business_management`
5. **App Review** (required for production):
   - Submit each permission with screencast showing Growth Engine flow.
   - Development mode works for app admins and test users only.
   - Typical review time: 3–10 business days.

### Instagram setup

1. Convert Instagram account to **Professional** (Business or Creator).
2. Link IG to a Facebook Page in **Meta Business Suite**.
3. Connect via **Growth → Platforms → Instagram** (resolves IG Business Account from linked Page).

## 4. Plan limits (Growth Engine gating)

Limits are enforced per subscription plan slug (`starter`, `professional`, `enterprise`):

| Plan | AI posts/month | Social platforms |
|------|----------------|------------------|
| Starter | 20 | 1 |
| Growth (professional) | 100 | 3 |
| Enterprise | 500 | 10 |

Configure in `config/growth.php`. Usage appears on **Subscription → Usage** and **Growth → Executive**.

Set Stripe Price IDs in **Admin → Plans** after creating prices in Stripe Dashboard.

## 5. Enable pilot client

```bash
php artisan growth:pilot-company owner@pilot-client.com
```

## 6. Client onboarding flow

1. Client logs into dashboard.
2. **Growth Engine → Platforms → Connect Facebook** or **Instagram**.
3. If multiple Pages: select Page (+ Ad Account for Facebook).
4. **Growth → Content**: generate posts, approve, publish.
5. For Instagram posts: add an image URL in `media_urls` (IG API requires media).
6. Share tracking links (`/g/{slug}`) in posts.
7. Customers click → WhatsApp → orders attribute automatically.

## 7. Sync jobs (manual or scheduled)

```bash
php artisan growth:sync-meta --company=1
php artisan growth:sync-meta --company=1 --metrics
php artisan growth:sync-meta --company=1 --ads
php artisan growth:sync-meta --company=1 --crm
```

Scheduled automatically:

| Job | Schedule |
|-----|----------|
| PublishScheduledPostsJob | Every 5 min |
| SyncMetaMetricsJob | Daily 06:00 |
| SyncMetaAdSpendJob | Daily 06:30 |
| ProcessCrmFollowUpsJob | Hourly |
| GeneratePortfolioRecommendationsJob | Mon 07:00 |
| PrunePortfolioRecommendationsJob | Sun 03:00 |
| SyncGrowthIntegrationsJob | Daily 05:00 |

## 8. Admin — Sapphital portfolio

- **Admin → Growth Portfolio** — cross-brand revenue
- `POST /api/admin/growth-portfolio/generate` — run portfolio AI now
- `POST /api/admin/growth-portfolio/recommendations/{id}/read` — mark recommendation read

## 9. External integrations (Layer 1 foundation)

- `GET /api/company/growth/integrations` — GA4, email, website status
- `POST /api/company/growth/integrations/connect` — connect website URL or store config
- `POST /api/company/growth/integrations/sync` — trigger sync

## 10. Verify attribution loop

1. Create post → copy tracking URL
2. Open URL in browser → redirects to WhatsApp
3. Send WhatsApp message with `ref:xxxx` code
4. Place order → check **Growth → Executive** for revenue attribution

## 11. E2E tests (frontend)

```bash
cd FRONTED
npm run test:e2e
```

Requires dev server or `PLAYWRIGHT_BASE_URL` pointing to deployed app.
