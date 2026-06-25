# Meta Developer Account & WhatsApp Cloud API — Admin Setup Guide

This guide is for **platform administrators** who configure the **Meta for Developers** app and link it to this product (Essem Chat Bot). Companies connect their own WhatsApp Business numbers in the dashboard; **you** set up the shared Meta app, webhook, and platform secrets.

**Official reference:** [Meta WhatsApp Cloud API documentation](https://developers.facebook.com/docs/whatsapp/cloud-api)

---

## What you need before you start

| Requirement | Notes |
|-------------|--------|
| **Meta account** | A Facebook/Meta account you can use for business. |
| **Meta Business Portfolio** | Often created when you create the app; you may need to claim or create a business. |
| **Public HTTPS URL for the backend** | Production webhook must use **HTTPS**. For local testing, use a tunnel (e.g. ngrok) and a temporary Callback URL. |
| **Access to Super Admin → Settings → Integrations** | Where you store the **webhook verify token** and **Meta App Secret** for this platform. |

---

## Part 1 — Create or open a Meta developer app

1. Go to **[Meta for Developers](https://developers.facebook.com/)** and sign in.
2. Open **My Apps** (top right) → **Create App** (or select an existing app).
3. When asked for an app type, choose an option that allows **WhatsApp** (commonly **Business** or **Other**; exact labels change over time). Complete the wizard:
   - App display name  
   - Contact email  
   - Business Portfolio (create or select one)
4. After the app is created, open it from **My Apps**.

---

## Part 2 — Add the WhatsApp product

1. In the app left sidebar, find **Add products** (or **Products** in the dashboard).
2. Locate **WhatsApp** and click **Set up** (or **Get started**).
3. You should now see **WhatsApp** in the sidebar with sub-items such as **API Setup**, **Configuration**, etc.

---

## Part 3 — Generate platform secrets (before the webhook)

You will paste these into **Super Admin → Settings → Integrations** in this product.

### 3.1 Webhook verify token (you invent this)

1. Choose a **long, random string** (e.g. 32+ characters). This is **not** provided by Meta; **you** create it.
2. In this product, open **Admin → Settings → Integrations**.
3. Set **WhatsApp webhook verify token** to that exact string and **Save**.
4. You will enter the **same** string in Meta as the **Verify token** (see Part 5).

Keep this value secret like a password; anyone with it could attempt webhook verification if they also control DNS/hosting.

### 3.2 Meta App Secret

1. In the Meta app, open **App settings** → **Basic** (sometimes under **Settings** in the left nav).
2. Find **App secret** → click **Show** and complete any security step Meta requires.
3. Copy the secret.
4. In this product, **Admin → Settings → Integrations**, set **Meta App Secret** and **Save**.

**Why:** The backend verifies `X-Hub-Signature-256` on incoming webhook POSTs when this secret is set. If the secret in Meta and in Integrations do not match, webhooks may return **403** and messages will not be processed.

**Optional:** If you leave **Meta App Secret** empty in Integrations (and no matching `.env` fallback), signature checks are skipped — useful only for quick local tests, not production.

---

## Part 4 — API Setup: Phone Number ID, tokens, and IDs

Use **WhatsApp → API Setup** in the Meta app (wording may be **Getting started** or **API setup**).

1. **Test number (from Meta)**  
   Meta provides a test phone number for development. You can send messages to **allowed recipient numbers** you add in the dashboard (Meta documents this under API Setup).

2. **Phone number ID**  
   Copy **Phone number ID** from this screen. Each WhatsApp Business **phone number** has its own ID. This platform routes traffic by `phone_number_id` in webhook payloads.

3. **Temporary access token**  
   Meta shows a **temporary** token for quick tests. It expires; do not use it as the long-term credential for production.

4. **Permanent / long-lived token (production)**  
   For a real business number, plan for a **System User** or **long-lived** token as described in Meta’s docs (Business Settings → System Users, or the token generation flow Meta provides for Cloud API). Exact clicks change; follow Meta’s current **“Get started”** or **“Access tokens”** guidance for WhatsApp Cloud API.

5. **WhatsApp Business Account ID (WABA ID)**  
   Note **WhatsApp Business Account ID** if shown — this product can store it when a company connects WhatsApp (optional field).

**Where tokens go in this product:**  
Each **company** enters **their** Phone Number ID and Access Token in **Dashboard → Settings → WhatsApp** (not in Meta-only admin screens). As **admin**, you only ensure the Meta app and webhook are correct; companies connect their number to the platform.

---

## Part 5 — Webhook configuration (Callback URL & subscription)

Meta must send events to this backend:

| Item | Value |
|------|--------|
| **Callback URL** | `https://YOUR_BACKEND_DOMAIN/api/whatsapp/webhook` |
| **Verify token** | The **same** string as **WhatsApp webhook verify token** in **Super Admin → Settings → Integrations** |
| **Subscription fields** | At minimum **messages** (this platform processes incoming messages) |

### Steps in Meta (WhatsApp → Configuration)

1. Open **WhatsApp** → **Configuration** (or **Webhook** under WhatsApp settings, depending on Meta’s UI).
2. **Callback URL:** enter your full URL, e.g. `https://api.yourdomain.com/api/whatsapp/webhook`.
3. **Verify token:** paste the verify token you saved in **Integrations** (must match **exactly**).
4. Click **Verify and save** (or equivalent). Meta sends a **GET** request to your URL; your server must respond with the `hub.challenge` value when the token matches. If verification fails, check HTTPS, firewall, and that the backend is reachable.
5. Under **Webhook fields** / **Subscribe to fields**, subscribe to **messages** (and any other fields Meta lists that you intend to use; **messages** is required for inbound chat).

**Production:** The Callback URL must be **HTTPS** with a valid certificate.

**Local testing:** Run your Laravel app, expose it with a tunnel (e.g. ngrok), and set Callback URL to `https://YOUR_TUNNEL_HOST/api/whatsapp/webhook`. Update the URL in Meta whenever the tunnel URL changes.

---

## Part 6 — Align environment and queue (server admin)

These are not “Meta dashboard” clicks but are required for the integration to work:

1. **`.env` (optional fallbacks)**  
   If Integrations are not filled in the database yet, you can use Laravel config fallbacks such as `WHATSAPP_WEBHOOK_VERIFY_TOKEN` and `META_APP_SECRET` — see `config/whatsapp.php` in the backend.

2. **Queue worker**  
   Incoming messages dispatch `ProcessIncomingWhatsAppMessage`. Run `php artisan queue:work` (or Supervisor/systemd) in production, or use `QUEUE_CONNECTION=sync` only for small/test setups.

3. **Graph API version**  
   The backend uses a configured Graph API base URL (see `config/whatsapp.php`, e.g. `v21.0`). Meta may deprecate old versions; upgrade when Meta requires it.

---

## Part 7 — Checklist (admin)

Use this before go-live:

- [ ] Meta app created and **WhatsApp** product added.
- [ ] **App Secret** copied into **Super Admin → Integrations → Meta App Secret**.
- [ ] **Webhook verify token** invented, saved in **Integrations**, and **identical** in Meta **Configuration → Verify token**.
- [ ] **Callback URL** points to `https://<your-domain>/api/whatsapp/webhook` and verification **succeeds**.
- [ ] Webhook subscribed to **messages**.
- [ ] Each company that should receive WhatsApp traffic has connected **their** Phone Number ID + token in **Dashboard → Settings → WhatsApp**.
- [ ] Queue worker running in production (or conscious choice of `sync`).
- [ ] SSL/HTTPS valid on the webhook host.

---

## Part 8 — Troubleshooting (Meta ↔ this platform)

| Symptom | What to check |
|---------|----------------|
| Webhook verification fails | HTTPS, exact verify token, no reverse-proxy stripping query params, Laravel route `GET /api/whatsapp/webhook` reachable. |
| POST returns 403 | **Meta App Secret** mismatch — fix secret in Integrations or clear it only for debugging. |
| Messages in Meta but nothing in app | Queue not running; wrong `phone_number_id` (company not connected); see `storage/logs/laravel.log`. |
| Token errors when sending | Expired token; use permanent token; company re-enters token in **Settings → WhatsApp**. |

For deeper product behavior (auto-reply, FAQ, OpenAI), see **`docs/WHATSAPP_SETUP.md`**.

---

## Quick reference — URLs in this product

| Purpose | Path |
|--------|------|
| Meta webhook (GET verify + POST events) | `/api/whatsapp/webhook` |
| Company connects WhatsApp (API) | `POST /api/company/whatsapp/connect` |

**Super Admin UI:** **Admin → Settings → Integrations** — WhatsApp webhook verify token, Meta App Secret, OpenAI (shared platform AI).

---

*Last aligned with Essem Chat Bot backend routes and `config/whatsapp.php`. Meta’s developer UI may rename menus; if a label differs, use Meta’s in-product help or the official Cloud API docs.*
