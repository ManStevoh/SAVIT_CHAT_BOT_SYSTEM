# WhatsApp Embedded Signup (Platform-Wide)

> **Full guide:** See [WhatsApp Complete Setup Guide](WHATSAPP_COMPLETE_SETUP_GUIDE.md) for Meta registration, App Review, pricing, billing, and step-by-step admin + company flows.

One Meta app serves all companies. Super admin configures once; companies connect via **Connect with Facebook**.

## Super admin setup

**Admin → Settings → Integrations** (or env fallbacks in `.env`):

| Setting | Description |
|---------|-------------|
| Webhook verify token | Meta App → WhatsApp → Configuration |
| Meta App Secret | Webhook signature validation |
| Embedded Signup App ID | Meta Business app ID |
| Embedded Signup Config ID | Embedded Signup Builder (v4) |
| Embedded App Secret | Server-side OAuth code exchange |
| OAuth redirect URI | Whitelist in Meta (default: `{APP_URL}/dashboard/settings`) |
| Enable coexist | Numbers already on WhatsApp Business mobile app |

**Webhook URL (Meta):** `https://YOUR_DOMAIN/api/whatsapp/webhook`

Monitor connections: **Admin → WhatsApp** (`/admin/whatsapp`)

## Meta one-time requirements (Savit)

1. Business verification  
2. App Review: `whatsapp_business_messaging`, `whatsapp_business_management`  
3. Tech Provider / Access Verification  
4. Embedded Signup v4 before Oct 15, 2026  

## Company flow

1. **Connect with Facebook** in Dashboard → Settings → WhatsApp  
2. Complete Meta popup (WABA + phone + OTP)  
3. Backend automatically:
   - Exchanges OAuth `code` → business token  
   - `POST /{WABA_ID}/subscribed_apps`  
   - `POST /{PHONE_NUMBER_ID}/register`  
4. Company can message immediately (after WABA payment method added)

## API endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/company/whatsapp/embedded/config` | Frontend SDK config |
| POST | `/api/company/whatsapp/embedded/complete` | Finish onboarding |
| GET | `/api/company/whatsapp/status` | Connection health |
| POST | `/api/company/whatsapp/disconnect` | Unsubscribe + deactivate |
| GET | `/api/company/whatsapp/templates` | List templates |
| POST | `/api/company/whatsapp/templates` | Create template |
| POST | `/api/company/whatsapp/templates/sync` | Sync from Meta |
| GET | `/api/admin/whatsapp/connections` | Admin monitor |

Manual token connect (`POST /api/company/whatsapp/connect`) is **disabled**.

## Troubleshooting

- **Embedded signup not enabled** — missing App ID, Config ID, or App Secret in admin settings  
- **Webhook subscription failed** — check verify token and app permissions  
- **Phone register failed** — number may be on another BSP; try coexist mode  
- **No inbound messages** — confirm webhook URL in Meta app; check `meta_app_secret` in production  
