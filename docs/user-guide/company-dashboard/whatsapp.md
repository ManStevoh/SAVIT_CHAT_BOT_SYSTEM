---
title: WhatsApp Connection
parent: Company Dashboard
nav_order: 18
---

# WhatsApp Connection

**URL:** `/dashboard/settings` (WhatsApp tab)

Connect your WhatsApp Business number in one click. **You do not need a Meta Developer account** — your platform administrator has already configured Meta for everyone.

## How to connect

1. Go to **Settings → WhatsApp Setup**
2. Click **Connect with Facebook**
3. Sign in with Facebook / Meta
4. Select or create your WhatsApp Business account
5. Add your phone number and complete OTP verification
6. Add a payment method to your WhatsApp Business account when Meta prompts you (required for messaging)

When finished, status shows **Connected** with your display number.

The platform automatically subscribes webhooks and registers your number — no manual tokens or webhook setup.

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
| "Embedded signup is not enabled" | Ask platform admin to complete Admin → Settings → Integrations |
| Popup blocked | Allow popups for this site |
| OTP not received | Use a number that can receive SMS/voice; avoid numbers on another API provider |
| Connected but no replies | Ensure queue worker runs; check AI auto-reply is on; verify subscription is active |
| Template rejected | Read rejection reason; edit and resubmit |

## Security

Access tokens are encrypted per company. If compromised, disconnect and reconnect via Facebook.
