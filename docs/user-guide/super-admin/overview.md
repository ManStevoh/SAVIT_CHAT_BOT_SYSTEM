---
title: Super Admin Overview
parent: Super Admin
nav_order: 1
---

# Super Admin Overview

**URL:** `/admin`

The super admin panel is for **platform operators** (Essem Digital Innovation Limited team) to manage all tenants, billing, integrations, and system health.

## Access

- Role: `admin` on user account
- Default seeded account: `admin@essem.local` / `password` (change immediately in production)
- Login at same URL as companies, then navigate to `/admin`

## Admin dashboard home

| Widget | Description |
|--------|-------------|
| Total companies | Active + suspended tenants |
| Active subscriptions | Paying customers |
| MRR / revenue | Monthly recurring revenue |
| Messages (platform-wide) | Aggregate WhatsApp volume |
| AI usage | Token consumption across tenants |
| System alerts | Queue failures, webhook errors |

## Admin navigation

| Section | URL | Purpose |
|---------|-----|---------|
| Overview | `/admin` | Platform metrics |
| Companies | `/admin/companies` | Tenant management |
| Users | `/admin/users` | User accounts |
| Plans | `/admin/plans` | Subscription plan CRUD |
| Subscriptions | `/admin/subscriptions` | All tenant subscriptions |
| Revenue | `/admin/revenue` | Financial reporting |
| Payment Gateways | `/admin/payment-gateways` | Stripe, M-Pesa, Paystack |
| Growth Portfolio | `/admin/growth` | Cross-brand growth insights |
| Testimonials | `/admin/testimonials` | Landing page social proof |
| Landing FAQs | `/admin/landing-faqs` | Public site FAQ section |
| Settings | `/admin/settings` | Platform-wide config |
| Logs | `/admin/logs` | System audit logs |
| AI Usage | `/admin/ai-usage` | OpenAI consumption by company |

## Key responsibilities

| Area | Admin action |
|------|--------------|
| Onboarding new platform | Configure Meta webhook, OpenAI, SMTP, payment gateways |
| Tenant support | Impersonate company, reset user passwords |
| Billing | Create/edit plans, view revenue |
| Compliance | Monitor logs, AI usage, suspend abusive accounts |
| Marketing site | Update landing testimonials, FAQs, branding |

## Impersonation

From Companies or Users:

- **Impersonate company** — log in as company owner
- **Impersonate user** — log in as specific user

Use for support only. Exit by logging out.

## System health

**Admin → Overview** or dedicated health endpoint shows:

- Queue worker status
- Failed jobs count
- Database connectivity
- Growth scheduler last run
- Webhook recent activity

See [Monitoring](monitoring.md).
