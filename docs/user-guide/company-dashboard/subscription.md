---
title: Subscription & Billing
parent: Company Dashboard
nav_order: 19
---

# Subscription & Billing

**URL:** `/dashboard/subscription`

Manage your plan, view usage, pay invoices, and access billing portal.

## Current plan

Displays:

- Plan name (Starter, Growth, Enterprise)
- Price and billing cycle
- Trial status and days remaining
- Renewal / expiry date
- Feature limits

## Usage meters

| Limit type | Description |
|------------|-------------|
| Messages / month | WhatsApp messages sent (bot + manual) |
| AI tokens | OpenAI usage (if tracked) |
| Growth AI posts | AI-generated social posts per month |
| Connected platforms | Social accounts for Growth Engine |

Usage resets monthly per billing cycle. Approaching limits shows warnings.

## Subscribe or upgrade

1. Select a plan
2. Choose payment method:
   - **Stripe Checkout** — redirect to secure card payment
   - **M-Pesa STK** — enter phone, approve on device
   - **Paystack** — if enabled by platform
3. Complete payment
4. Subscription activates immediately (or after trial)

## Free trial

Starter and Growth include **14 days free**:

- Full features during trial
- Card may be required upfront depending on Stripe config
- After trial: auto-charge or downgrade per plan settings

## Billing portal

Click **Manage Billing** to open Stripe Customer Portal:

- Update payment method
- View invoices
- Cancel subscription

## Invoices

View and download past invoices from the subscription page or Stripe portal.

## Expired subscription

When subscription expires:

- Bot sends "service unavailable" to WhatsApp customers
- Dashboard shows renewal banner
- Subscription/checkout routes remain accessible
- Other dashboard features may be restricted

Renew promptly to restore bot service.

## Enterprise

Enterprise plan shows **Contact Sales** CTA. Platform admin assigns custom pricing and limits manually.
