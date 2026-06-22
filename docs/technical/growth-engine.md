---
title: Growth Engine
parent: Technical Documentation
nav_order: 9
---

# Growth Engine (Technical)

Closed-loop system: **post → click → WhatsApp → lead → order → revenue**

## Components

```
┌──────────────────────────────────────────────────────────────┐
│                     Growth Engine                            │
├──────────────┬───────────────┬──────────────┬────────────────┤
│ Content AI   │ Publishing    │ Attribution  │ Intelligence   │
│ Generation   │ Scheduler     │ Tracking     │ & Learning     │
├──────────────┼───────────────┼──────────────┼────────────────┤
│ Social OAuth │ Meta Sync     │ CRM Agent    │ Portfolio AI   │
│ Ad Spend     │ Competitors   │ Integrations │ Weekly Briefs  │
└──────────────┴───────────────┴──────────────┴────────────────┘
```

## Services (`app/Services/Growth/`)

| Service | Purpose |
|---------|---------|
| `GrowthContentService` | AI post generation |
| `GrowthPublishService` | Publish to connected platforms |
| `AttributionService` | Create links, track events |
| `GrowthAnalyticsService` | Funnel metrics, ROI |
| `GrowthMetaService` | Facebook/Instagram Graph API |
| `GrowthOAuthService` | OAuth for LinkedIn, TikTok, X |
| `GrowthIntelligenceService` | Patterns, briefs, scoring |
| `GrowthCrmService` | Follow-up WhatsApp messages |
| `GrowthCompetitorService` | Competitor tracking |
| `GrowthAgentOrchestrator` | Multi-agent pipeline |
| `GrowthAdSpendService` | Ad spend import/ROI |
| `GrowthIntegrationService` | GA4, email sync |
| `PortfolioRecommendationService` | Admin cross-brand AI |

## Attribution flow

```
1. SocialPost created
2. AttributionService creates AttributionLink (unique slug)
3. Public URL: GET /g/{slug} (web route, not /api)
4. Controller logs AttributionEvent (type: click)
   - IP and User-Agent hashed (GDPR)
5. Redirect to WhatsApp with prefill: ref:{slug}
6. WhatsApp webhook receives message with ref code
7. Chat linked: chats.attribution_link_id, social_post_id
8. AttributionEvent (type: whatsapp_start)
9. Order created → events: lead, order, revenue
10. Analytics aggregates funnel metrics
```

## OAuth flow

```
1. GET /api/company/growth/oauth/{platform}/authorize
2. Redirect to platform OAuth
3. Platform redirects → GET /oauth/growth/callback
4. GrowthOAuthService exchanges code for tokens
5. SocialAccount stored (encrypted tokens)
6. Frontend redirect: /dashboard/growth?growth_oauth=success
```

**Redirect URI:** `{APP_URL}/oauth/growth/callback`

### Platform env vars

| Platform | Variables |
|----------|-----------|
| Meta (FB/IG) | `GROWTH_META_APP_ID`, `GROWTH_META_APP_SECRET` |
| LinkedIn | `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET` |
| TikTok | `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET` |
| X | `TWITTER_CLIENT_ID`, `TWITTER_CLIENT_SECRET` |

## Publishing pipeline

```
Draft → approve → schedule (optional) → publish
                              ↓
              PublishScheduledPostsJob (every 5 min)
                              ↓
              GrowthPublishService → platform API
                              ↓
              Update social_posts.status = published
```

Human-in-the-loop: posts require explicit `approve` before schedule/publish.

## Plan limits (`config/growth.php`)

| Plan slug | AI posts/month | Max platforms |
|-----------|----------------|---------------|
| starter | 20 | 1 |
| professional | 100 | 3 |
| enterprise | 500 | 10 |

Enforced in content generation and OAuth connect endpoints.

## Scheduled jobs

| Job | Schedule | Purpose |
|-----|----------|---------|
| `PublishScheduledPostsJob` | Every 5 min | Publish due posts |
| `SyncMetaMetricsJob` | Daily 06:00 | Sync FB/IG metrics |
| `SyncMetaAdSpendJob` | Daily 06:30 | Import Meta ad spend |
| `ProcessCrmFollowUpsJob` | Hourly | CRM WhatsApp follow-ups |
| `ExtractGrowthPatternsJob` | Weekly Mon 08:00 | Pattern learning |
| `GenerateWeeklyBriefJob` | Weekly Mon 08:30 | AI weekly brief |
| `ScorePostPerformanceJob` | Daily 07:00 | Pre-publish scoring |
| `GeneratePortfolioRecommendationsJob` | Weekly Mon 07:00 | Admin portfolio |
| `PrunePortfolioRecommendationsJob` | Weekly Sun 03:00 | Cleanup old recs |
| `SyncGrowthIntegrationsJob` | Daily 05:00 | External integrations |

Defined in `bootstrap/app.php`.

## CRM agent

`ProcessCrmFollowUpsJob` + `GrowthCrmService`:

- Targets leads who clicked attribution but didn't order
- Respects `GROWTH_CRM_HOURS_QUIET` and `GROWTH_CRM_MAX_FOLLOW_UPS`
- Sends WhatsApp follow-up via company connected number

## Intelligence API

| Endpoint | Purpose |
|----------|---------|
| `GET .../intelligence/patterns` | Learned content patterns |
| `POST .../patterns/extract` | Trigger extraction |
| `POST .../patterns/{id}/apply` | Apply pattern to generation |
| `GET .../content-mix` | Weekly content type plan |
| `POST .../execute-mix` | Generate posts from mix |
| `GET .../score-drafts` | Revenue prediction scores |
| `POST .../generate-smart` | Mimic top performers |
| `GET .../weekly-brief` | AI performance summary |
| `GET .../export/attribution` | CSV export |

## Admin portfolio API

| Method | Path |
|--------|------|
| GET | `/api/admin/growth-portfolio` |
| POST | `/api/admin/growth-portfolio/generate` |
| POST | `/api/admin/growth-portfolio/queue` |

## Frontend

- Page: `LARAVEL_BACKEND/resources/js/Pages/dashboard/growth/page.tsx`
- Hooks: `useGrowthAnalytics`, `useGrowthPosts`, etc. in `lib/api-hooks.ts`
- E2E tests: `LARAVEL_BACKEND/e2e/dashboard.spec.ts`, `journey-company.spec.ts`

## Deployment requirements

```bash
# Cron (every minute)
* * * * * cd /path/to/LARAVEL_BACKEND && php artisan schedule:run

# Queue worker (always running)
php artisan queue:work --tries=3
```

Install helper: `php artisan growth:scheduler-install`

## Compliance

- Attribution IPs hashed before storage
- Official platform APIs only (no scraping)
- Human approval before publish
- OAuth tokens encrypted at rest

## Related legacy docs

- [GROWTH_ENGINE_SPEC.md (legacy)](../GROWTH_ENGINE_SPEC.md)
- [GROWTH_DEPLOYMENT.md (legacy)](../GROWTH_DEPLOYMENT.md)
