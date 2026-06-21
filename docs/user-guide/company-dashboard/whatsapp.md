---
title: WhatsApp Connection
parent: Company Dashboard
nav_order: 18
---

# WhatsApp Connection

**URL:** `/dashboard/settings` (WhatsApp tab)

Connect your WhatsApp Business number so the platform can receive and send messages on your behalf.

## What you need from Meta

Before connecting, set up a Meta Developer app with WhatsApp product:

1. [Meta for Developers](https://developers.facebook.com/) account
2. WhatsApp Business Account (WABA)
3. **Phone Number ID** — from WhatsApp → API Setup
4. **Permanent Access Token** — system user or long-lived token

Platform admin must configure the shared webhook (see Super Admin docs). Your company only connects **your number**.

## Connection methods

### Method 1: Manual connect

1. Go to **Settings → WhatsApp**
2. Enter **Phone Number ID**
3. Enter **Access Token**
4. Optional: display phone number, WABA ID
5. Click **Connect**
6. Status should show **Connected** with your number

### Method 2: Embedded Signup (if enabled)

1. Click **Connect with Meta**
2. Complete Meta's embedded signup flow in popup
3. Select your WhatsApp Business account and phone number
4. Platform stores credentials automatically

Requires platform admin to configure `WHATSAPP_EMBEDDED_*` credentials.

## Verify connection

| Check | Expected |
|-------|----------|
| Dashboard status | Green "Connected" badge |
| API status endpoint | `connected: true`, correct phoneNumberId |
| Test message | Send "Hi" from personal WhatsApp → receive greeting |

## Disconnect

Click **Disconnect** to remove WhatsApp credentials. Bot stops replying; existing chat history remains.

## What platform admin configures (not you)

| Setting | Why platform-wide |
|---------|-------------------|
| Webhook URL | One URL for all companies: `/api/whatsapp/webhook` |
| Webhook verify token | Meta verification |
| Meta App Secret | Webhook signature validation |
| OpenAI API key | Shared AI provider |

## Troubleshooting

### No auto-reply after connecting

Check in order:

1. **Queue worker running** on server (`php artisan queue:work`) — most common production issue
2. **Auto-reply enabled** in Settings → AI
3. **Active subscription** — expired plans show unavailable message
4. **Meta webhook** subscribed to "messages" with correct callback URL
5. **Meta App Secret** matches between admin settings and Meta app
6. **Escalation keywords** — message contains "agent"/"human" skips bot

### Wrong company receives messages

Phone Number ID must match the number registered in Meta. Each company has unique `phone_number_id` in database.

### Messages in dashboard but not sent to customer

Check Laravel logs for WhatsApp send API errors (invalid token, expired token, rate limits).

## Security

Access tokens are **encrypted** in the database. Rotate tokens in Meta if compromised, then reconnect in dashboard.
