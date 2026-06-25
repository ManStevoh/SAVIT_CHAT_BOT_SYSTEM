---
title: Environment Variables
parent: Technical Documentation
nav_order: 12
---

# Environment Variables

Complete reference for `.env` configuration. Admin UI settings in `platform_settings` override many integration values when set.

## Backend (`LARAVEL_BACKEND/.env`)

### Application

| Variable | Example | Description |
|----------|---------|-------------|
| `APP_NAME` | Essem Chat Bot | Application name |
| `APP_ENV` | production | local / production |
| `APP_KEY` | base64:... | Encryption key (required) |
| `APP_DEBUG` | false | Never true in production |
| `APP_URL` | https://essemchat.essemglobalsolutions.com | Backend public URL |
| `FRONTEND_URL` | https://essem-chat-bot-system.vercel.app | CORS allowed origin |
| `SANCTUM_STATEFUL_DOMAINS` | essem-chat-bot-system.vercel.app | Comma-separated SPA domains |

### Database

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_CONNECTION` | sqlite | sqlite / mysql / pgsql |
| `DB_HOST` | 127.0.0.1 | Database host |
| `DB_PORT` | 3306 | Database port |
| `DB_DATABASE` | laravel | Database name |
| `DB_USERNAME` | root | Database user |
| `DB_PASSWORD` | | Database password |

### Queue, cache, session

| Variable | Default | Description |
|----------|---------|-------------|
| `QUEUE_CONNECTION` | database | database / sync / redis |
| `CACHE_STORE` | database | Cache driver |
| `SESSION_DRIVER` | database | Session driver |

### Mail

| Variable | Description |
|----------|-------------|
| `MAIL_MAILER` | smtp |
| `MAIL_HOST` | SMTP host |
| `MAIL_PORT` | 587 |
| `MAIL_USERNAME` | SMTP user |
| `MAIL_PASSWORD` | SMTP password |
| `MAIL_ENCRYPTION` | tls |
| `MAIL_FROM_ADDRESS` | noreply@example.com |
| `MAIL_FROM_NAME` | Essem Chat Bot |

Prefer Admin → Settings for production SMTP (stored in DB).

### Stripe

| Variable | Description |
|----------|-------------|
| `STRIPE_KEY` | Publishable key |
| `STRIPE_SECRET` | Secret key |
| `STRIPE_WEBHOOK_SECRET` | Webhook signing secret |
| `STRIPE_TRIAL_DAYS` | Default trial days |
| `STRIPE_CURRENCY` | usd |

### Subscription defaults

| Variable | Description |
|----------|-------------|
| `SUBSCRIPTION_DEFAULT_PLAN_SLUG` | starter |
| `SUBSCRIPTION_DEFAULT_TRIAL_DAYS` | 14 |

### WhatsApp / Meta

| Variable | Description |
|----------|-------------|
| `WHATSAPP_WEBHOOK_VERIFY_TOKEN` | Meta webhook verification |
| `META_APP_SECRET` | Webhook signature validation |
| `WHATSAPP_EMBEDDED_APP_ID` | Embedded signup app ID |
| `WHATSAPP_EMBEDDED_CONFIG_ID` | Embedded signup config |
| `WHATSAPP_EMBEDDED_APP_SECRET` | Embedded signup secret |
| `WHATSAPP_EMBEDDED_REDIRECT_URI` | OAuth redirect |
| `WHATSAPP_DEFAULT_ACCESS_TOKEN` | Dev/testing fallback |

### OpenAI

| Variable | Default | Description |
|----------|---------|-------------|
| `OPENAI_API_KEY` | | API key |
| `OPENAI_MODEL` | gpt-4o-mini | Model name |
| `OPENAI_MAX_TOKENS` | 512 | Max response tokens |

### Conversation tuning

| Variable | Description |
|----------|-------------|
| `CONVERSATION_LOG_ROUTING` | true/false — log bot routing |
| `FAQ_DIRECT_MIN_SCORE` | Min FAQ match score for direct reply |

### Growth Engine

| Variable | Description |
|----------|-------------|
| `GROWTH_META_APP_ID` | Meta app for Growth OAuth |
| `GROWTH_META_APP_SECRET` | Meta app secret |
| `LINKEDIN_CLIENT_ID` | LinkedIn OAuth |
| `LINKEDIN_CLIENT_SECRET` | LinkedIn OAuth |
| `TIKTOK_CLIENT_KEY` | TikTok OAuth |
| `TIKTOK_CLIENT_SECRET` | TikTok OAuth |
| `TWITTER_CLIENT_ID` | X OAuth |
| `TWITTER_CLIENT_SECRET` | X OAuth |
| `GROWTH_DEFAULT_CURRENCY` | USD |
| `GROWTH_CRM_HOURS_QUIET` | Quiet hours for CRM agent |
| `GROWTH_CRM_MAX_FOLLOW_UPS` | Max follow-up messages |
| `GROWTH_PORTFOLIO_PRUNE_DAYS` | Days to keep portfolio recs |
| `GROWTH_GA4_*` | Google Analytics 4 integration |
| `GROWTH_EMAIL_SYNC_*` | Email integration settings |

### Seeder

| Variable | Default | Description |
|----------|---------|-------------|
| `SUPER_ADMIN_EMAIL` | admin@essem.local | Seed admin email |
| `SUPER_ADMIN_PASSWORD` | password | Seed admin password |

### AWS (optional)

| Variable | Description |
|----------|-------------|
| `AWS_ACCESS_KEY_ID` | S3 storage |
| `AWS_SECRET_ACCESS_KEY` | S3 storage |
| `AWS_DEFAULT_REGION` | us-east-1 |
| `AWS_BUCKET` | Bucket name |

## Frontend (Inertia — `LARAVEL_BACKEND/.env`)

The UI is served from Laravel; API calls use same-origin `/api/*`. No separate `NEXT_PUBLIC_*` env file is required for production.

| Variable | Required | Description |
|----------|----------|-------------|
| `APP_URL` | Yes | Public app URL (used for links and assets) |
| `VITE_*` | No | Only if you add custom Vite env vars |

### Playwright (testing)

| Variable | Description |
|----------|-------------|
| `PLAYWRIGHT_BASE_URL` | http://localhost:3000 |
| `PLAYWRIGHT_SKIP_SERVER` | Skip starting dev server |
| `PLAYWRIGHT_REUSE_SERVER` | Reuse existing server |
| `PLAYWRIGHT_SERVER_CMD` | Custom start command |

## Priority: DB vs env

For platform integrations (WhatsApp verify token, OpenAI key, SMTP):

1. **Admin UI value** (platform_settings table) — used if set
2. **`.env` value** — fallback
3. **Empty** — feature disabled or error

Company-level settings (greeting, M-Pesa shortcode) stored in `company_settings` only.

## Security notes

- Never commit `.env` to git
- Use different keys for staging vs production
- Rotate compromised keys immediately
- `APP_KEY` must never change in production without re-encrypting data
