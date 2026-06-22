# Order Payments – Close Sales Automatically

This document describes how companies can collect payment from customers when they place an order via the WhatsApp bot: **M-Pesa (STK push)** and **Stripe (card)**. When payment is received, the order is marked paid and the customer gets a WhatsApp confirmation.

---

## Overview

1. **Company setup**  
   In **Dashboard → Settings → Order Payments**, the company enables **M-Pesa** and/or **Card (Stripe)** for customer orders.

2. **Bot flow**  
   After the customer confirms the order (product → quantity → address → confirm), the bot:
   - If at least one payment method is enabled: asks **How would you like to pay? 1. M-Pesa 2. Card**.
   - **M-Pesa:** Asks for the phone number (use current chat number or reply with another). Sends STK push to that number; customer enters PIN. When Safaricom calls back, the order is marked paid and a WhatsApp confirmation is sent.
   - **Card:** Sends a Stripe payment link on WhatsApp. Customer pays in the browser. When Stripe webhook fires, the order is marked paid and a WhatsApp confirmation is sent.

3. **Manual override**  
   In **Dashboard → Orders**, staff can change **Payment status** (Pending / Paid / Refunded) and **Order status** for reconciliation.

---

## Admin: Payment gateway configuration (platform default)

The **platform** M-Pesa and Stripe configuration is used for subscription payments and as the **default** for order payments when a company does not set its own.

### M-Pesa (platform default)

- **Admin → Payment gateways → M-Pesa**: Consumer Key, Consumer Secret, Shortcode, Passkey, **Callback URL**.
- **Callback URL** must be the public URL Safaricom calls for STK result, e.g.  
  `https://your-backend.com/api/mpesa/callback`  
  The same URL handles both **subscription** and **order** payments; the backend distinguishes them by cache key.

### Stripe (platform default)

- **Admin → Payment gateways → Stripe**: Secret key, Webhook secret.
- **Webhook** must receive `checkout.session.completed`. The backend treats it as:
  - **Order payment** if `metadata.order_id` is present (one-time payment for an order).
  - **Subscription** otherwise (subscription checkout).

For **order** payments, currency is taken from gateway config (`order_currency` or `currency`). Default is `usd`.

---

## What a company needs for M-Pesa (Lipa Na M-Pesa Online)

The integration uses **Lipa Na M-Pesa Online** (STK push): the customer gets a prompt on their phone and enters their M-Pesa PIN. Money is sent to the business shortcode (PayBill or Till).

### Till type: PayBill **or** Buy Goods and Services (Till)

A company can use **either** of these, depending on what they have with Safaricom:

| Type | What it is | Shortcode | In dashboard |
|------|------------|-----------|---------------|
| **PayBill** | Business PayBill number (e.g. 6 digits). Customer pays to PayBill X, Account Y. | PayBill number | Choose "PayBill", enter shortcode + passkey |
| **Till (Buy Goods and Services)** | Lipa Na M-Pesa Till number. Used by shops / merchants. | Till number | Choose "Till (Buy Goods)", enter shortcode + passkey |

Both use the same **Lipa Na M-Pesa Online** passkey from Safaricom (the one linked to that shortcode for STK push).

### What the company must have

1. **Shortcode**  
   - **PayBill**: The business PayBill number (e.g. `174379` in sandbox).  
   - **Till**: The Buy Goods and Services (till) number.

2. **Lipa Na M-Pesa Online passkey**  
   - Issued by Safaricom for that shortcode when they enable **Lipa Na M-Pesa Online** (STK push) for the business.  
   - Not the same as the normal M-Pesa PIN or agent credentials.

3. **Optional: own Daraja app**  
   - If the company has its own app on [developer.safaricom.co.ke](https://developer.safaricom.co.ke), they can enter **Consumer Key** and **Consumer Secret** so API calls use their app.  
   - If left blank, the **platform’s** Daraja app is used (platform admin must have set Consumer Key/Secret in Payment gateways); the company still uses their own **shortcode + passkey** so money goes to their PayBill/Till.

4. **Environment**  
   - **Sandbox**: For testing (Safaricom test credentials).  
   - **Production**: Live PayBill/Till and passkey.

The **callback URL** is always the platform’s (so the app receives the payment result). Companies do not set a callback URL.

---

## Company: Enable payment collection and methods

1. Go to **Dashboard → Settings → Order Payments**.
2. **Collect payment for orders** – Turn **on** to ask the customer how to pay after they confirm the order. Turn **off** to skip payment and only confirm the order (e.g. pay on delivery).
3. When collection is on, enable **M-Pesa**, **Card (Stripe)**, and/or add **Manual payment instructions** (bank account, PayBill to pay manually, etc.). The bot will offer these as options (1. M-Pesa 2. Card 3. Pay manually) or, if only manual is set, send the instructions directly.
4. Optionally set **your own** automated payment details so money goes to you:
   - **M-Pesa**: Choose **PayBill** or **Till (Buy Goods)**, then Shortcode, Passkey; optionally Daraja Consumer Key/Secret and environment (sandbox/production). Callback URL stays the platform one.
   - **Stripe account**: Secret key (sk_live_... or sk_test_...) and optional currency.
4. Save.

- **Collect payment** turned **off**: After order confirm the bot only says "Order confirmed! … We'll prepare it and contact you for delivery." No payment step.
- **Manual instructions only** (no M-Pesa/Stripe): After confirm the bot sends the order summary and your manual payment text (e.g. bank details, PayBill to pay manually). Customer pays offline and can reply when done.
- **Automated (M-Pesa and/or Stripe)** with or without manual: Bot asks "How would you like to pay? 1. M-Pesa 2. Card 3. Pay manually." Choice 3 shows your manual instructions.

If a company does **not** set its own M-Pesa/Stripe config, the **platform** config is used (admin must have configured it). The payment step is only shown when **Collect payment** is on and at least one of M-Pesa, Stripe, or manual instructions is set.

---

## Bot flow (detailed)

| Step | Bot message / action |
|------|----------------------|
| Order confirmed | Order #X – Total: Y. How would you like to pay? 1. M-Pesa 2. Card |
| Customer: 1 or M-Pesa | We'll send an M-Pesa payment request to your phone. Use this number (254…) or reply with a different number. |
| Customer: YES or another number | STK push sent. "We've sent an M-Pesa payment request… Enter your PIN. You'll get a confirmation here once payment is received." |
| M-Pesa callback (success) | Order `payment_status` → paid, `status` → confirmed; WhatsApp: "Payment received. Your order #X is confirmed. Thank you!" |
| Customer: 2 or Card | Bot sends Stripe payment link. "Pay by card here: {url}. Reply once you've completed payment." |
| Stripe checkout completed | Same: order marked paid, WhatsApp confirmation sent. |

---

## API

- **PATCH /api/company/orders/:id**  
  Body: `{ "status": "confirmed" }` and/or `{ "paymentStatus": "paid" }`.  
  Used by the dashboard to update order and payment status manually.

- **GET/PUT /api/company/settings**  
  Response includes `ordersAcceptMpesa`, `ordersAcceptStripe` (boolean), `orderPaymentMpesaConfigured`, `orderPaymentStripeConfigured` (boolean; never returns raw secrets).  
  Body can include `orderPaymentMpesaConfig` (object: shortcode, passkey, optional consumer_key, consumer_secret, env) and/or `orderPaymentStripeConfig` (object: secret, optional currency). Send `null` to clear company config and fall back to platform.

---

## Files (reference)

| Area | Files |
|------|--------|
| Order payment service | `LARAVEL_BACKEND/app/Services/OrderPaymentService.php` |
| Order flow (steps + payment) | `LARAVEL_BACKEND/app/Services/OrderFlowService.php` |
| M-Pesa callback (order + subscription) | `LARAVEL_BACKEND/app/Http/Controllers/Api/MpesaCallbackController.php` |
| Stripe one-time session + webhook | `LARAVEL_BACKEND/app/Services/StripeService.php`, `StripeWebhookController.php` |
| Company settings (payment flags) | `LARAVEL_BACKEND/app/Models/CompanySetting.php`, `SettingsController.php` |
| Orders (chat_id, PATCH) | `LARAVEL_BACKEND/app/Models/Order.php`, `OrderController.php` |
| Frontend: settings | `LARAVEL_BACKEND/resources/js/Pages/dashboard/settings/page.tsx` (Order Payments tab) |
| Frontend: orders | `LARAVEL_BACKEND/resources/js/Pages/dashboard/orders/page.tsx` (payment status in modal) |
| Stripe success/cancel page | `LARAVEL_BACKEND/resources/js/Pages/order-paid/page.tsx` |

---

## Migration

Run:

```bash
cd LARAVEL_BACKEND && php artisan migrate
```

This adds:

- `company_settings.orders_accept_mpesa`, `orders_accept_stripe`
- `company_settings.order_payment_mpesa_config` (JSON, nullable), `order_payment_stripe_config` (JSON, nullable) for company-owned credentials
- `orders.chat_id` (nullable, for sending WhatsApp after payment)
