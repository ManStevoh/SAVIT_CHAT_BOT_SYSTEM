# Stripe Checkout Setup

## 1. Environment

In `.env` set:

- `STRIPE_KEY` – Publishable key (e.g. `pk_test_...`)
- `STRIPE_SECRET` – Secret key (e.g. `sk_test_...`)
- `STRIPE_WEBHOOK_SECRET` – Webhook signing secret (e.g. `whsec_...`)
- `STRIPE_TRIAL_DAYS` – Optional; default `14`
- `STRIPE_CURRENCY` – Optional; default `usd`

## 2. Stripe Dashboard

1. Create **Products** and **Prices** (recurring monthly/yearly) in [Stripe Dashboard → Products](https://dashboard.stripe.com/products).
2. Copy each **Price ID** (e.g. `price_1ABC...`).
3. In **Admin → Plans**, edit each plan and paste the **Stripe Price ID**. Plans with a Price ID show “Subscribe” and use Stripe Checkout; plans without show “Contact Sales”.

## 3. Webhook

1. In [Stripe Dashboard → Developers → Webhooks](https://dashboard.stripe.com/webhooks), add an endpoint:
   - **URL:** `https://your-api-domain.com/api/stripe/webhook`
   - **Events:** `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`
2. Copy the **Signing secret** and set `STRIPE_WEBHOOK_SECRET` in `.env`.

**Local testing with Stripe CLI:**

```bash
stripe listen --forward-to http://localhost:8000/api/stripe/webhook
```

Use the printed `whsec_...` as `STRIPE_WEBHOOK_SECRET` in `.env`.

## 4. Flow

- **Checkout:** Company user clicks Subscribe → `POST /api/company/checkout` with `planId` → backend creates a Stripe Checkout Session (subscription + trial) → frontend redirects to Stripe.
- **Success/cancel:** Stripe redirects back to `FRONTEND_URL/dashboard/subscription?checkout=success` or `?checkout=cancelled`.
- **Webhook:** Stripe sends events to `/api/stripe/webhook`; backend creates/updates the `subscriptions` record and links `companies.stripe_customer_id`.
- **Billing portal:** “Manage billing” calls `POST /api/company/billing-portal` and redirects to Stripe’s customer portal (update payment, cancel, etc.).

## 5. Migrations

Run after adding Stripe env vars:

```bash
php artisan migrate
```

This adds `stripe_price_id` (plans), `stripe_customer_id` (companies), and `stripe_subscription_id` (subscriptions).
