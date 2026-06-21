---
title: Backend
parent: Technical Documentation
nav_order: 4
---

# Backend Application

**Path:** `LARAVEL_BACKEND/`  
**Framework:** Laravel 12

## Directory structure

```
app/
├── Http/
│   ├── Controllers/Api/          # REST API controllers
│   │   ├── Admin/                # Super admin endpoints
│   │   ├── Company/              # Tenant endpoints
│   │   └── *.php                 # Public, auth, webhooks
│   └── Middleware/
│       ├── EnsureUserIsAdmin.php
│       └── EnsureSubscriptionActive.php
├── Services/                     # Business logic
│   ├── AI/                       # OpenAI prompt building
│   ├── Conversation/             # FAQ, classification, routing
│   ├── Growth/                   # 20+ Growth Engine services
│   └── *.php                     # Orders, payments, WhatsApp
├── Jobs/                         # Queued async work
│   └── Growth/                   # Growth scheduled jobs
├── Models/                       # 36 Eloquent models
└── Console/Commands/           # Artisan commands

routes/
├── api.php                       # All /api/* routes
├── web.php                       # Attribution, OAuth, receipts
└── console.php                   # CLI commands

config/
├── whatsapp.php, openai.php, stripe.php
├── subscription.php, growth.php, conversation.php
└── cors.php, sanctum.php

database/
├── migrations/                   # 53 migrations
└── seeders/                      # Plans, admin, sample data
```

## Middleware

| Alias | Class | Behavior |
|-------|-------|----------|
| `auth:sanctum` | Sanctum | Validates Bearer token |
| `admin` | EnsureUserIsAdmin | Requires `role = admin` |
| `subscription.active` | EnsureSubscriptionActive | Blocks if no active subscription |

Subscription middleware applies to all `/api/company/*` routes except subscription/checkout paths.

## Service layer pattern

Controllers delegate to services:

```php
// Example flow
ChatController → ChatService / existing models
ProcessIncomingWhatsAppMessage → AIReplyService, FaqMatchingService, OrderFlowService
GrowthPostController → GrowthContentService, AttributionService
```

## Key services

| Service | Responsibility |
|---------|----------------|
| `AIReplyService` | OpenAI chat completion with business context |
| `FaqMatchingService` | Fuzzy FAQ match with confidence score |
| `CustomerMessageClassifier` | Intent detection (order, FAQ, greeting) |
| `OrderFlowService` | Multi-step order state machine |
| `OrderPaymentService` | M-Pesa STK + Stripe link generation |
| `WhatsAppMessageSenderService` | Meta send message API |
| `PlanLimitService` | Message/Growth quota enforcement |
| `StripeService` | Checkout sessions, billing portal |
| `MpesaService` | STK push initiation |
| `PaystackService` | Payment initialization |

## Jobs

| Job | Trigger | Purpose |
|-----|---------|---------|
| `ProcessIncomingWhatsAppMessage` | WhatsApp webhook | Bot reply pipeline |
| `Growth\PublishScheduledPostsJob` | Scheduler every 5 min | Publish due posts |
| `Growth\SyncMetaMetricsJob` | Daily 06:00 | Sync social metrics |
| `Growth\SyncMetaAdSpendJob` | Daily 06:30 | Import ad spend |
| `Growth\ProcessCrmFollowUpsJob` | Hourly | CRM WhatsApp follow-ups |
| `Growth\ExtractGrowthPatternsJob` | Weekly Mon 08:00 | ML pattern extraction |
| `Growth\GenerateWeeklyBriefJob` | Weekly Mon 08:30 | AI weekly summary |
| `Growth\ScorePostPerformanceJob` | Daily 07:00 | Pre-publish scoring |
| `Growth\GeneratePortfolioRecommendationsJob` | Weekly Mon 07:00 | Admin portfolio AI |
| `Growth\SyncGrowthIntegrationsJob` | Daily 05:00 | GA4/email sync |

## Web routes (`routes/web.php`)

| Route | Purpose |
|-------|---------|
| `GET /g/{slug}` | Attribution click tracking → WhatsApp redirect |
| `GET /oauth/growth/callback` | OAuth token exchange for Growth platforms |
| `GET /order/{order}/receipt` | Signed order receipt view |

## Platform settings

Stored in `platform_settings` table, managed via Admin UI. Services read via helper that checks DB first, `.env` fallback.

## Encryption

- WhatsApp access tokens encrypted at rest (`whatsapp_accounts.access_token`)
- Sensitive gateway keys in `payment_gateways` table

## Artisan commands

| Command | Purpose |
|---------|---------|
| `php artisan migrate` | Run migrations |
| `php artisan db:seed` | Seed plans, admin, gateways |
| `php artisan queue:work` | Process queue jobs |
| `php artisan schedule:run` | Run scheduled tasks (via cron) |
| `php artisan growth:health` | Growth Engine diagnostics |
| `php artisan growth:scheduler-install` | Install cron helper |
| `php artisan subscription:expiry-reminders` | Manual expiry email run |

## Health endpoint

`GET /up` — Laravel built-in health check for load balancers and uptime monitors.

## Testing

```bash
cd LARAVEL_BACKEND
php artisan test                    # All tests
php artisan test --filter=Growth    # Growth Engine tests
```

Test coverage includes: FAQ matching, attribution flow, Paystack webhooks, Growth intelligence.
