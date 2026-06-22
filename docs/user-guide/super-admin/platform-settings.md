---
title: Platform Settings
parent: Super Admin
nav_order: 45
---

# Super Admin: Platform Settings

**URL:** `/admin/settings`

Platform-wide configuration affecting all tenants. Organized in tabs.

## General tab

| Setting | Description |
|---------|-------------|
| Allow new registrations | When off, `/register` is blocked at the API |
| Require email verification | When on, new users must verify email before login (requires SMTP in Email tab) |
| Maintenance mode | Shows maintenance message; blocks normal access |
| Default timezone | Platform-wide date/time boundaries |

When email verification is **off** (default), registrations auto-verify and users can sign in immediately. When **on**, registration sends a verification email; login returns `email_not_verified` until the link is clicked.

## Integrations tab

### WhatsApp (platform)

| Setting | Description |
|---------|-------------|
| Webhook verify token | Must match Meta App → WhatsApp → Configuration |
| Meta App Secret | Validates webhook POST signatures (recommended) |
| Embedded Signup App ID | For company self-service WhatsApp connect |
| Embedded Config ID | Meta embedded signup configuration |
| Default access token | Fallback for testing |

**Webhook URL (Meta):** `https://your-backend.com/api/whatsapp/webhook`

One webhook serves all companies; backend routes by `phone_number_id`.

### OpenAI

| Setting | Description |
|---------|-------------|
| API key | Shared across all companies |
| Model | Default `gpt-4o-mini` |
| Max tokens | Response length limit |

Companies control greeting, tone, and auto-reply — not the API key.

## Email (SMTP)

| Setting | Description |
|---------|-------------|
| Mail driver | SMTP recommended |
| Host, port, encryption | Provider settings |
| Username, password | SMTP credentials |
| From address / name | Platform sender identity |

Click **Test email** to verify configuration.

Emails sent: verification, password reset, new order notifications, subscription reminders. See [Email Touchpoints (legacy)](../../EMAIL_TOUCHPOINTS.md).

## Branding

| Setting | Description |
|---------|-------------|
| Platform name | Shown in emails and UI |
| Logo URL | Landing and auth pages |
| Primary color | Theme |
| Favicon | Browser tab icon |
| Landing hero text | Marketing copy |
| Footer text | Landing page footer |

Public endpoints serve branding: `GET /api/app-branding`, `GET /api/landing`

## Legal / compliance

| Setting | Description |
|---------|-------------|
| Terms URL | Link on registration |
| Privacy URL | Link on registration |
| Support email | Contact on landing |

## Timezone

Platform default timezone affects:

- Scheduled jobs (Growth publish, subscription reminders)
- Working hours evaluation
- Analytics date boundaries

See [Platform Timezone (legacy)](../../PLATFORM_TIMEZONE.md).

## Meta Developer setup

Full Meta app setup guide: [Meta Developer Account Setup (legacy)](../../META_DEVELOPER_ACCOUNT_SETUP.md)

## Environment fallback

Settings in admin UI are stored in `platform_settings` database table. `.env` values serve as fallback when DB setting is empty.
