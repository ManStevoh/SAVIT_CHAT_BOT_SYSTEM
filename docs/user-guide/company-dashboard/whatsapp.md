---
title: WhatsApp Connection
parent: Company Dashboard
nav_order: 18
---

# WhatsApp Connection

**URL:** `/dashboard/settings` (WhatsApp tab)

Connect your WhatsApp Business number via **Connect with Facebook** (recommended) or **manual credentials** if your platform administrator enabled that option.

## How to connect — Facebook (recommended)

Requires super admin to enable **Embedded Signup**.

1. Go to **Settings → WhatsApp Setup**
2. Click **Connect with Facebook**
3. Sign in with Facebook / Meta
4. Select or create your WhatsApp Business account
5. Add your phone number and complete OTP verification
6. Add a payment method to your WhatsApp Business account when Meta prompts you (required for messaging)

When finished, status shows **Connected** with your display number.

## How to connect — Manual

Requires super admin to enable **manual connection** (often available during platform setup).

1. In [Meta Developer Console](https://developers.facebook.com) → your app → **WhatsApp → API Setup**, copy:
   - **Phone number ID**
   - **Permanent access token** (system user token with `whatsapp_business_messaging` + `whatsapp_business_management`)
   - **WhatsApp Business Account ID** (optional but recommended)
2. Go to **Settings → WhatsApp Setup** → **Manual connection**
3. Paste the values and click **Connect manually**

The platform verifies your token with Meta, subscribes webhooks, and registers your number automatically.

## Message templates

After connecting, use the **Message templates** section on the same tab to:

- **Sync from Meta** — pull approved templates
- **Submit to Meta** — create utility/marketing/authentication templates (Meta approval required)

## Verify connection

| Check | Expected |
|-------|----------|
| Dashboard status | Green "Connected" badge |
| Webhook subscribed | Yes |
| Phone registered | Yes |
| Test message | Send "Hi" from personal WhatsApp → receive greeting |

## Disconnect

Click **Disconnect** to unsubscribe webhooks and deactivate the number. Chat history remains.

## Troubleshooting

| Issue | Fix |
|-------|-----|
| No connect options shown | Ask platform admin to enable Embedded Signup and/or manual connection in Admin → Settings → Integrations |
| "Embedded signup is not enabled" | Use manual connection if enabled, or wait for admin to turn on Embedded Signup |
| Manual token rejected | Regenerate token; confirm Phone Number ID matches; check token permissions |
| Popup blocked | Allow popups for this site (Facebook flow only) |
| OTP not received | Use a number that can receive SMS/voice; avoid numbers on another API provider |
| Connected but no replies | Ensure queue worker runs; check AI auto-reply is on; verify subscription is active |
| Template rejected | Read rejection reason; edit and resubmit |

## Security

Access tokens are encrypted per company. If compromised, disconnect and reconnect.
