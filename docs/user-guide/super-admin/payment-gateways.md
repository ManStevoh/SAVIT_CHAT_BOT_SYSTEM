---
title: Payment Gateways
parent: Super Admin
nav_order: 44
---

# Super Admin: Payment Gateways

**URL:** `/admin/payment-gateways`

Configure platform-wide payment providers used for **subscriptions** and as **defaults for order payments**.

## Supported gateways

| Gateway | Use cases |
|---------|-----------|
| **Stripe** | Card subscriptions, card order payments, billing portal |
| **M-Pesa** | STK push for subscriptions and order payments (Kenya) |
| **Paystack** | Alternative card/mobile money (Africa) |

## Stripe configuration

| Field | Description |
|-------|-------------|
| Publishable key | Frontend (if needed) |
| Secret key | Server-side API calls |
| Webhook secret | Verify `/api/stripe/webhook` signatures |
| Currency | Default USD or local |
| Order currency | Currency for one-time order checkouts |

**Webhook events:** Must include `checkout.session.completed` at minimum.

Webhook URL: `https://your-backend.com/api/stripe/webhook`

## M-Pesa configuration

| Field | Description |
|-------|-------------|
| Consumer Key | Daraja API app key |
| Consumer Secret | Daraja API secret |
| Shortcode | PayBill or Till number |
| Passkey | Lipa Na M-Pesa Online passkey |
| Callback URL | `https://your-backend.com/api/mpesa/callback` |
| Environment | sandbox / production |

Register callback URL in Safaricom Daraja portal.

Same callback handles subscription and order STK results (distinguished by cache key).

## Paystack configuration

| Field | Description |
|-------|-------------|
| Secret key | API authentication |
| Public key | Frontend initialization |
| Webhook secret | Verify `/api/paystack/webhook` |

Webhook URL: `https://your-backend.com/api/paystack/webhook`

## Platform vs company credentials

| Payment type | Default credentials | Company override |
|--------------|--------------------|------------------|
| Subscription | Platform only | No |
| Order payment | Platform default | Company can set own M-Pesa/Stripe in Settings |

When company sets own credentials, order payments go to their PayBill/Till or Stripe account.

## Testing

Use sandbox/test keys before production:

- Stripe test mode: `sk_test_...`
- M-Pesa sandbox shortcode from Safaricom developer portal
- Paystack test keys

## Troubleshooting

| Issue | Check |
|-------|-------|
| Subscription not activating | Stripe webhook delivery logs |
| M-Pesa STK not arriving | Shortcode, passkey, callback URL reachable |
| Order paid but status pending | Webhook signature, metadata.order_id in session |
| Wrong currency | Gateway currency settings |
