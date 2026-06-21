# WhatsApp End-to-End Integration Setup

This document describes how the WhatsApp bot flow works and how to complete setup with the Meta WhatsApp Cloud API.

## Flow Overview

1. **Customer** sends a message on WhatsApp to your business number.
2. **Meta** sends a webhook POST to your backend: `POST /api/whatsapp/webhook`.
3. **Backend** saves the message to the database (creates/updates chat and message), then dispatches a job `ProcessIncomingWhatsAppMessage`.
4. **Job** checks company settings (`auto_reply_enabled`), gets a reply using:
   - Greeting (first message)
   - Keyword triggers (e.g. "price", "catalog", "order")
   - FAQ match (questions/answers and keywords)
   - OpenAI (if no FAQ match and `OPENAI_API_KEY` is set)
   - Fallback message
5. **Backend** sends the reply via Meta’s “send message” API so the **customer sees it in WhatsApp** (company WhatsApp inbox).
6. **Agent replies** from the dashboard are also sent to WhatsApp when the company has a connected WhatsApp account.

Replies go **directly to the company’s WhatsApp conversation** with the customer (the customer sees them in their WhatsApp app). The app dashboard is for the business to view chats and reply manually when needed.

## Configuration: Who Sets What?

There are two levels: **Super Admin (platform)** and **Company (each business)**.

### Super Admin only (platform-wide)

Configured in **Admin → Settings → Integrations**. All companies use these.

| Setting | Used for | Why platform-wide? |
|--------|----------|--------------------|
| **WhatsApp webhook verify token** | Meta GET webhook verification | There is **one** webhook URL for the whole app. Meta calls it to verify and to send all messages; the backend finds the company by `phone_number_id` in the payload. |
| **Meta App Secret** | Verifying webhook POST signature | Same webhook endpoint for the entire platform. |
| **OpenAI API key, model, max tokens** | AI replies when FAQ doesn’t match | Shared OpenAI usage; the platform pays for API calls. Companies only control **behavior** (greeting, tone, on/off), not the key or model. |

### Company level (per business)

Each company configures their own in **Dashboard → Settings** (and **Settings → WhatsApp** for connection).

| Setting | Where | Used for |
|--------|--------|----------|
| **WhatsApp connection** | Dashboard → Settings → **WhatsApp** tab | **Their** Phone Number ID + Access Token. Each company connects **their own** WhatsApp Business number; messages go to/from that number. |
| **AI greeting** | Dashboard → Settings (e.g. **AI** or **FAQ** tab) | Custom first-message text when a customer says hi. |
| **AI tone** | Same | Tone passed to OpenAI (e.g. “friendly and professional”). |
| **Auto-reply enabled** | Same | Turn bot auto-replies on/off for that company. |
| **FAQs, products** | Dashboard → FAQs, Products | Company-specific knowledge and catalog used for keyword/FAQ replies and AI context. |

### Summary

- **WhatsApp “which number”** → each company sets their own (connect in Dashboard).
- **WhatsApp “how the app receives Meta webhooks”** → Super Admin only (one URL, one verify token, one app secret).
- **AI “which key and model”** → Super Admin only (shared).
- **AI “how the bot behaves”** (greeting, tone, on/off, FAQs, products) → each company sets their own.

So: **companies do not set** webhook verify token, Meta app secret, or OpenAI API key/model. They **do set** their own WhatsApp number connection, greeting, tone, auto-reply switch, FAQs, and products.

## Backend Requirements

- **Queue worker** must be running so `ProcessIncomingWhatsAppMessage` runs (e.g. `php artisan queue:work`).
- **WhatsApp & OpenAI settings** are stored in the database and managed in **Super Admin → Settings → Integrations**:
  - **WhatsApp webhook verify token** – same value as in Meta App → WhatsApp → Configuration → Callback verify token.
  - **Meta App Secret** (optional) – for webhook signature verification (recommended in production).
  - **OpenAI API key** (optional) – for AI replies when FAQ doesn’t match.
  - **OpenAI model** (e.g. `gpt-4o-mini`), **OpenAI max tokens** (e.g. 512).
  You can still set these in `.env` as an optional fallback if not set in the admin UI.

## Meta App Setup

1. Go to [Meta for Developers](https://developers.facebook.com/), create or select an app, add **WhatsApp** product.
2. In **WhatsApp → API Setup**:
   - Get your **Phone number ID** and **Permanent access token** (or use a system user token).
   - Note your **WhatsApp Business Account ID** if needed.
3. In **WhatsApp → Configuration**:
   - **Callback URL:** `https://YOUR_BACKEND_DOMAIN/api/whatsapp/webhook` (must be HTTPS in production).
   - **Verify token:** Set the same string as in **Super Admin → Settings → Integrations** (WhatsApp webhook verify token), or in `.env` as fallback.
   - Subscribe to **messages**.
4. In the app dashboard (Settings → WhatsApp), enter **Phone Number ID** and **Access Token** and connect.

## API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/whatsapp/webhook` | Meta webhook verification (query: `hub.mode`, `hub.verify_token`, `hub.challenge`). |
| POST | `/api/whatsapp/webhook` | Meta sends incoming messages and status updates here. |
| POST | `/api/company/whatsapp/connect` | Connect WhatsApp (body: `phoneNumberId`, `accessToken`, optional `displayPhoneNumber`, `whatsappBusinessAccountId`). |
| GET | `/api/company/whatsapp/status` | Get connection status (connected, phoneNumberId, displayPhoneNumber). |
| POST | `/api/company/whatsapp/disconnect` | Disconnect WhatsApp for the company. |

## Database

- **whatsapp_accounts:** `company_id`, `phone_number_id`, `access_token` (encrypted), `display_phone_number`, `status`, etc.
- **messages:** `whatsapp_message_id` added for deduplication of incoming messages.

## Troubleshooting: connected in the app but no auto-reply on WhatsApp

Check these in order:

1. **Queue worker (most common on production)**  
   Incoming messages enqueue `ProcessIncomingWhatsAppMessage`. With `QUEUE_CONNECTION=database` (the default), **nothing sends until a worker runs**:
   - SSH on the server: `php artisan queue:work` (or use Supervisor/systemd to keep it running).  
   - Or set `QUEUE_CONNECTION=sync` in `.env` so jobs run in the same PHP process as the webhook (simpler for small servers; the HTTP request may take longer while the reply is generated).  
   - After deploying, confirm `jobs` table rows are processed (or use `php artisan queue:listen` while testing).

2. **Auto-reply enabled**  
   Dashboard → **Settings** → **AI Settings** → ensure **Auto-reply** is on. If `auto_reply_enabled` is false, the job exits without sending a bot message (notifications may still be sent if enabled).

3. **Active subscription**  
   Without an active subscription, the customer gets a short “service unavailable” style message instead of a normal AI reply. If they get **nothing at all**, the problem is usually the queue (step 1) or webhook (steps 4–5).

4. **Meta webhook**  
   Callback URL must match your live API host (e.g. `https://YOUR_BACKEND_DOMAIN/api/whatsapp/webhook`). In Meta: **WhatsApp → Configuration**, subscribe to **messages**, and use the same verify token as in Super Admin → Integrations.

5. **Meta App Secret**  
   If **Meta App Secret** is set in the admin UI but does not match the app in Meta, Meta’s POST may get **403** and no message is stored. Fix the secret or clear it to match `.env` / Meta.

6. **Escalation keywords**  
   Messages containing words like **agent**, **human**, **support** (see `ProcessIncomingWhatsAppMessage::wantsHumanEscalation`) skip the bot reply and only notify the company.

7. **Logs**  
   `storage/logs/laravel.log`: look for `WhatsApp webhook: unknown phone_number_id` (connection mismatch), `signature verification failed`, or `ProcessIncomingWhatsAppMessage` lines. Check `failed_jobs` if using the database queue.

## Testing Locally

1. Expose your local backend with a tunnel (e.g. ngrok): `ngrok http 8000`.
2. Use the ngrok HTTPS URL as Callback URL in Meta (e.g. `https://xxx.ngrok.io/api/whatsapp/webhook`).
3. Run migrations and queue worker: `php artisan migrate`, `php artisan queue:work`.
4. Connect WhatsApp in the app dashboard and send a message to your business number from another WhatsApp account.
