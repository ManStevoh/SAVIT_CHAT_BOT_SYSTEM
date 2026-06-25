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

### WhatsApp (platform — configure once)

Super admin sets up **one Meta app** for the entire platform. Companies connect via **Embedded Signup** (recommended) or **manual credentials** if you enable that option.

| Setting | Description |
|---------|-------------|
| Webhook verify token | Must match Meta App → WhatsApp → Configuration |
| Meta App Secret | Validates webhook POST signatures (required in production) |
| Embedded Signup App ID | Your Meta Business app ID |
| Embedded Signup Config ID | From Embedded Signup Builder (v4) |
| Embedded App Secret | Used server-side to exchange OAuth codes |
| OAuth redirect URI | Whitelist in Meta; default `{APP_URL}/dashboard/settings` |
| Enable coexist | Allow numbers already on WhatsApp Business mobile app |
| **Enable Embedded Signup** | Turn on **Connect with Facebook** for companies (off during Meta App Review) |
| **Enable manual connection** | Allow companies to paste Phone Number ID + access token from Meta Developer Console |

**Webhook URL (set in Meta once):** `https://your-domain.com/api/whatsapp/webhook`

Status indicators in the UI:

- **Credentials complete** — App ID, Config ID, and Secret are saved  
- **Live for companies** — Embedded Signup toggle is on and credentials are complete  

When a company completes **Connect with Facebook**, the platform automatically:

1. Exchanges the OAuth code for a business token  
2. Subscribes webhooks on their WABA  
3. Registers their phone for Cloud API  

Monitor all connections at **Admin → WhatsApp** (`/admin/whatsapp`).

**Meta requirements (one-time for Essem):** Business verification, App Review (`whatsapp_business_messaging` + `whatsapp_business_management`), Tech Provider access verification. Companies must add a payment method to their WABA (unless you are a Solution Partner with shared billing).

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
