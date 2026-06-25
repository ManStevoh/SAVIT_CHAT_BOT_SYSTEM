# Essem Growth Engine — Technical Specification

**Module:** AI Social Media Growth Engine with closed-loop attribution  
**Stack:** Laravel (API) + Next.js (dashboard) + Meta Graph API + OpenAI  
**Compliance:** GDPR-ready attribution (hashed IPs), human-in-the-loop publishing, official platform APIs only

---

## Overview

The Growth Engine extends Essem Chat Bot from WhatsApp-only commerce to a full **post → click → WhatsApp → lead → order → revenue** growth loop.

### Layers implemented

| Layer | Feature | Status |
|-------|---------|--------|
| 1 | Data collection (social accounts, metrics sync) | ✅ |
| 2 | AI content intelligence | ✅ |
| 3 | AI content generation | ✅ |
| 4 | Scheduled publishing | ✅ (cron every 5 min) |
| 5 | Conversion attribution | ✅ |
| 6 | Competitor profiles | ✅ |
| 7 | Executive dashboard | ✅ |
| — | Multi-agent orchestration | ✅ |
| — | Admin portfolio view | ✅ |
| 4 | Performance learning loop | ✅ |
| 4 | Pre-publish revenue prediction | ✅ |
| 4 | Smart generate from winners | ✅ |
| 4 | Weekly content mix optimizer | ✅ |

---

## Database tables

- `social_accounts` — OAuth / manual platform connections per company
- `social_posts` — draft, scheduled, published content
- `social_post_metrics` — reach, clicks, engagement (synced or estimated)
- `attribution_links` — short links `/g/{slug}`
- `attribution_events` — click, whatsapp_start, lead, order, revenue
- `competitor_profiles`, `competitor_snapshots`
- `growth_insights`, `growth_agent_runs`
- `chats.social_post_id`, `chats.attribution_link_id`, `orders.social_post_id`

---

## API endpoints

### Company (auth + active subscription)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/company/growth/analytics` | Executive summary, funnel, top posts |
| GET | `/api/company/growth/posts` | List posts |
| POST | `/api/company/growth/posts` | Create manual post + tracking link |
| POST | `/api/company/growth/content/generate` | AI generate drafts |
| POST | `/api/company/growth/posts/{id}/approve` | Approve for publish |
| POST | `/api/company/growth/posts/{id}/schedule` | Schedule publish |
| POST | `/api/company/growth/posts/{id}/publish` | Publish now |
| DELETE | `/api/company/growth/posts/{id}` | Delete draft |
| GET | `/api/company/growth/social-accounts` | List connections |
| POST | `/api/company/growth/social-accounts/connect` | Connect platform |
| POST | `/api/company/growth/social-accounts/{id}/disconnect` | Disconnect |
| GET | `/api/company/growth/insights` | List AI insights |
| POST | `/api/company/growth/insights/generate` | Generate new insights |
| POST | `/api/company/growth/insights/{id}/read` | Mark read |
| GET | `/api/company/growth/competitors` | List competitors |
| POST | `/api/company/growth/competitors` | Add competitor |
| DELETE | `/api/company/growth/competitors/{id}` | Remove |
| GET | `/api/company/growth/agents` | Agent run history |
| POST | `/api/company/growth/agents/run` | Dispatch agent pipeline |
| POST | `/api/company/growth/content/generate-smart` | AI posts mimicking top winners |
| GET | `/api/company/growth/intelligence/patterns` | Learning patterns |
| POST | `/api/company/growth/intelligence/patterns/extract` | Extract patterns from performance |
| POST | `/api/company/growth/intelligence/patterns/{id}/apply` | Apply a pattern |
| GET | `/api/company/growth/intelligence/content-mix` | Weekly mix plan |
| GET | `/api/company/growth/intelligence/weekly-brief` | Latest weekly brief |
| GET | `/api/company/growth/intelligence/score-drafts` | Pre-publish predictions |
| POST | `/api/company/growth/intelligence/execute-mix` | Generate posts from mix plan |
| GET | `/api/company/growth/intelligence/summary` | Intelligence summary |

### Admin

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/admin/growth-portfolio` | Cross-brand portfolio metrics |

### Public

| Method | Path | Description |
|--------|------|-------------|
| GET | `/g/{slug}` | Track click → redirect to WhatsApp |

---

## Attribution flow

1. Post created → `AttributionLink` with slug + WhatsApp prefill containing `ref:{slug}`
2. User clicks `/g/{slug}` → `click` event logged (IP/UA hashed)
3. User messages WhatsApp with ref code → chat linked to post
4. Order created → `order` + `revenue` events attributed to post

---

## Plan limits (`config/growth.php`)

| Plan | AI posts/month | Platforms |
|------|----------------|-----------|
| starter | 20 | 1 |
| professional | 100 | 3 |
| enterprise | 500 | 10 |

---

## Frontend

- Dashboard: `/dashboard/growth`
- Hooks: `useGrowthAnalytics`, `useGrowthPosts`, etc. in `lib/api-hooks.ts`
- Actions: `lib/api-actions.ts`

---

## Scheduler

`PublishScheduledPostsJob` runs every 5 minutes via `bootstrap/app.php`.

---

## OAuth (Meta, LinkedIn, TikTok, X)

1. Set credentials in `.env` (see `.env.example`)
2. Register redirect URI in each developer console: `{APP_URL}/oauth/growth/callback`
3. Dashboard → Growth → Platforms → **Connect {platform}**
4. User completes OAuth → redirected to `/dashboard/growth?growth_oauth=success`

| Platform | Env vars |
|----------|----------|
| Facebook / Instagram | `GROWTH_META_APP_ID`, `GROWTH_META_APP_SECRET` (or WhatsApp embedded app) |
| LinkedIn | `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET` |
| TikTok | `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET` |
| X (Twitter) | `TWITTER_CLIENT_ID`, `TWITTER_CLIENT_SECRET` |

## Ad spend & ROI

- `GET/POST /api/company/growth/ad-spend`
- `POST /api/company/growth/ad-spend/import` (CSV)
- Executive summary includes: `adSpend`, `costPerLead`, `customerAcquisitionCost`, `roi`

## Production scheduler

```bash
php artisan growth:scheduler-install
```

Adds cron guidance for `schedule:run` (publishes scheduled posts every 5 minutes).

## Testing

```bash
cd LARAVEL_BACKEND
php artisan migrate
php artisan test --filter=Growth
php artisan test --filter=Attribution
```
