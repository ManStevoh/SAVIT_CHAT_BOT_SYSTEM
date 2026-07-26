---
title: Settings
parent: Company Dashboard
nav_order: 17
---

# Settings

**URL:** `/dashboard/settings`

Configure how your business appears and how the bot behaves. Settings are organized in tabs.

## Business profile

| Setting | Description |
|---------|-------------|
| Company name | Display name |
| Category | Business type (restaurant, retail, etc.) |
| Location / address | Used in location keyword replies |
| Working hours | Open/close times per day |
| Delivery charges | Shown during order flow |
| Social links | Optional footer in messages |
| Timezone | Affects working hours and scheduling |

## AI settings

| Setting | Description |
|---------|-------------|
| **Auto-reply enabled** | Master switch for bot responses |
| **Greeting message** | First message when customer says hi |
| **Away message** | Sent outside working hours |
| **AI tone** | Passed to OpenAI (e.g. "friendly and professional") |
| **Fallback message** | When bot cannot determine reply |

**Note:** OpenAI API key and model are set by platform admin — you control behavior, not the key.

## Branding

| Setting | Description |
|---------|-------------|
| Logo | Company logo in dashboard |
| Primary color | Theme accent |
| Receipt footer | Text on order receipts |

## Catalog currency & display

Prices in the dashboard, mobile app, WhatsApp catalog, invoices, and AI replies use your catalog currency settings:

| Setting | Description |
|---------|-------------|
| **Catalog currency** | ISO 4217 code (e.g. USD, KES, EUR) |
| **Currency symbol** | Optional label shown before amounts (e.g. `KSh`, `€`). Leave empty to use the currency code / locale default |
| **Thousands separator** | Grouping mark: `,` · `.` · space · `'` (e.g. `1,000` vs `1.000`) |
| **Decimal separator** | `.` or `,` (auto-paired when you change thousands) |

**Web:** Dashboard → Settings (Business / catalog currency section)  
**Mobile:** More → Currency

Example: symbol `KSh`, thousands `.`, decimal `,` → `KSh 1.234,56`

Tax rates (VAT/GST) are configured under **Dashboard → Taxes** (mobile: **More → Taxes**).

## Order payments

Configure how customers pay after confirming an order:

| Setting | Description |
|---------|-------------|
| **Collect payment** | Ask payment method after order confirm |
| **M-Pesa** | Enable STK push; PayBill or Till shortcode + passkey |
| **Stripe (card)** | Enable payment links; optional own Stripe secret key |
| **Manual instructions** | Bank details, PayBill account number for offline pay |
| **Currency** | Gateway checkout currency (Stripe); catalog display is separate — see above |

See [Payments customer journey](../customer-journeys/payments.md) for bot flow details.

## Working hours behavior

When a message arrives outside working hours:

1. Away message is sent (if configured)
2. Bot may still collect orders depending on settings
3. Notifications still alert your team

## Saving changes

Click **Save** on each tab. Changes apply immediately to new incoming messages.

## WhatsApp tab

WhatsApp connection has its own detailed guide: [WhatsApp Connection](whatsapp.md).
