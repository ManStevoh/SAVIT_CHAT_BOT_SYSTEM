---
title: Payments
parent: Technical Documentation
nav_order: 8
---

# Payments Integration

Three payment gateways integrated for **subscriptions** and **order payments**.

## Architecture

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│ Company checkout│────▶│ Laravel Services │────▶│ Stripe/M-Pesa/  │
│ WhatsApp bot    │     │ Webhook handlers │     │ Paystack APIs   │
└─────────────────┘     └──────────────────┘     └─────────────────┘
                                │
                                ▼
                        Update Subscription
                        or Order payment_status
```

## Stripe

### Use cases

1. **Subscription checkout** — `POST /api/company/checkout`
2. **Billing portal** — `POST /api/company/billing-portal`
3. **Order card payment** — Stripe Checkout link sent on WhatsApp

### Services

- `StripeService` — session creation, customer management
- `StripeWebhookController` — event processing

### Webhook

**URL:** `POST /api/stripe/webhook`  
**Verification:** `Stripe-Signature` header with webhook secret

| Event | Action |
|-------|--------|
| `checkout.session.completed` | Activate subscription OR mark order paid |
| `customer.subscription.updated` | Update subscription status |
| `customer.subscription.deleted` | Cancel subscription |
| `invoice.paid` | Record invoice |

Order vs subscription distinguished by `metadata.order_id` in checkout session.

### Config

Admin: `/admin/payment-gateways/stripe`  
Env: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_CURRENCY`

See `LARAVEL_BACKEND/STRIPE.md` for setup.

## M-Pesa (Safaricom Daraja)

### Use cases

1. **Subscription STK** — `POST /api/company/mpesa/initiate`
2. **Order STK** — initiated by `OrderPaymentService` during bot flow

### Flow

```
1. Backend calls Daraja STK Push API
2. Customer receives prompt on phone
3. Safaricom POST → /api/mpesa/callback
4. MpesaCallbackController parses result
5. Cache key distinguishes subscription vs order
6. Update payment status + send WhatsApp confirmation
```

### Services

- `MpesaService` — OAuth token, STK push
- `MpesaCallbackController` — callback handler

### Company vs platform credentials

| Field | Platform | Company override |
|-------|----------|------------------|
| Consumer Key/Secret | Default Daraja app | Optional own app |
| Shortcode | Platform default | Company PayBill/Till |
| Passkey | Platform default | Company passkey |
| Callback URL | Always platform URL | Not configurable |

Money goes to shortcode owner's account.

### Config

Admin: `/admin/payment-gateways/mpesa`  
Env: Daraja credentials in `.env.example`

## Paystack

### Use cases

- Subscription initialization — `POST /api/company/paystack/initialize`
- Alternative to Stripe in supported regions

### Webhook

**URL:** `POST /api/paystack/webhook`  
**Verification:** HMAC SHA512 signature

Service: `PaystackService`, `PaystackWebhookController`

## Order payment flow (bot)

Handled by `OrderFlowService` + `OrderPaymentService`:

```
Order confirmed
    ↓
Check company_settings.order_payments.collect_payment
    ↓
Present payment options (M-Pesa / Stripe / manual)
    ↓
M-Pesa: collect phone → STK push → callback
Stripe: generate Checkout session URL → send on WhatsApp
Manual: send instructions text
    ↓
Mark order payment_status = paid
    ↓
Send WhatsApp confirmation + optional receipt URL
```

## Subscription payment flow

```
Dashboard → Subscription → Select plan
    ↓
Stripe: redirect to Checkout → webhook activates
M-Pesa: STK push → callback activates
Paystack: initialize → webhook activates
    ↓
Subscription record created/updated
Plan limits applied via PlanLimitService
```

## Middleware interaction

Subscription checkout routes bypass `subscription.active` middleware so expired users can renew.

## Security

- Webhook signatures verified before processing
- Gateway secrets encrypted in DB
- Order checkout metadata includes signed order ID
- M-Pesa callback validates Safaricom response structure

## Testing

| Gateway | Test mode |
|---------|-----------|
| Stripe | `sk_test_...` keys, test card 4242... |
| M-Pesa | Sandbox shortcode from developer.safaricom.co.ke |
| Paystack | Test keys from dashboard |

PHPUnit tests: `tests/Feature/PaystackWebhookTest.php`

## Currency

- Stripe order currency: gateway `order_currency` or `currency` field
- M-Pesa: KES
- Default subscription currency: `STRIPE_CURRENCY` env (usd default)

## Manual payment reconciliation

Staff PATCH order with `payment_status: paid` — no webhook involved. Used for offline/bank payments.

## Related legacy docs

- [ORDER_PAYMENTS.md (legacy)](../ORDER_PAYMENTS.md)
