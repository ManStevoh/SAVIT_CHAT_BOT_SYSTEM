---
title: Plans & Billing
parent: Super Admin
nav_order: 43
---

# Super Admin: Plans & Billing

**URLs:** `/admin/plans`, `/admin/subscriptions`, `/admin/revenue`

## Plans management

**URL:** `/admin/plans`

Create and edit subscription plans shown on landing page and company checkout.

### Plan fields

| Field | Description |
|-------|-------------|
| Name | Display name (Starter, Growth, Enterprise) |
| Slug | Internal ID (starter, professional, enterprise) |
| Price display | e.g. "$29", "Custom" |
| Price amount | Numeric for sorting/filtering |
| Description | Plan subtitle |
| Features | Bullet list shown on pricing cards |
| Popular badge | Highlight recommended plan |
| CTA text | Button label |
| Stripe Price ID | Link to Stripe product/price |
| Trial days | Free trial length (14 default) |
| Trial elapsed action | downgrade / suspend after trial |
| Sort order | Display order on pricing page |

### Default seeded plans

| Plan | Slug | Price | Messages/mo | Growth posts |
|------|------|-------|-------------|--------------|
| Starter | starter | $29 | 5,000 | 20 |
| Growth | professional | $99 | 50,000 | 100 |
| Enterprise | enterprise | Custom | Unlimited | 500 |

## Subscriptions

**URL:** `/admin/subscriptions`

View all tenant subscriptions:

- Company name
- Plan
- Status (active, trialing, past_due, cancelled, expired)
- Stripe subscription ID
- Current period start/end
- Trial end date

Filter by status or plan. Use for support and revenue ops.

## Revenue dashboard

**URL:** `/admin/revenue`

| Metric | Description |
|--------|-------------|
| MRR | Monthly recurring revenue |
| Total revenue | All-time platform revenue |
| Revenue by plan | Breakdown chart |
| Recent transactions | Stripe/M-Pesa/Paystack payments |
| Churn | Cancelled subscriptions |

## Stripe integration

Plans must have valid **Stripe Price IDs** for card checkout. Configure Stripe keys in [Payment Gateways](payment-gateways.md).

Webhook at `/api/stripe/webhook` handles:

- `checkout.session.completed` — new subscription or order payment
- Subscription lifecycle events

See `LARAVEL_BACKEND/STRIPE.md` for setup details.

## Manual subscription management

For Enterprise or special cases, admin can:

- Assign plan directly to company (via company edit)
- Extend trial outside Stripe
- Comp subscription by creating manual subscription record

## Expiry reminders

Scheduled command `subscription:expiry-reminders` runs daily at 09:00 platform time. Sends email reminders before expiry.
