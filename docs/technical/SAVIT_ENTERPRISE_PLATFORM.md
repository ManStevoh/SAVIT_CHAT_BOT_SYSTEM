---
title: SAVIT Enterprise Platform Blueprint
parent: Home
nav_order: 7
description: Master registry for Subscription Engine, Billing, Notifications, Portals, Builders, Operations modules — verified gap analysis and phased build plan aligned with global SaaS standards.
---

# SAVIT Enterprise Platform Blueprint

**Verified against production code on 2026-07-11.**

This document maps **every operational platform module** in the SAVIT vision to **real code** (or honest gaps). It complements the AI stack docs:

- [Digital Nervous System](SAVIT_DIGITAL_NERVOUS_SYSTEM.md) — AI consciousness & agent intelligence
- [AI ABI Platform](AI_ABI_PLATFORM.md) — shipped AI layers (Agent → Cognitive)
- [AI Phase 5 OS](AI_PHASE5_OS.md) — executive dashboard, approvals, voice, A/B experiments
- [Enterprise Platform Phase 2](ENTERPRISE_PLATFORM_PHASE2.md) — entitlements, billing ledger, API keys (v1 shipped)

**Framing:** SAVIT is not only an AI product. It is a **multi-tenant business operating system** where AI agents need rich operational data, permissions, billing, and events to act safely at scale.

---

## Status legend

| Status | Meaning |
|--------|---------|
| **Implemented** | End-to-end feature with API/UI/tests in production paths |
| **Partial** | Adjacent capability exists; missing core engine or enterprise depth |
| **Roadmap** | Designed direction; no production implementation |

---

## Executive summary

| Category | Modules | Implemented | Partial | Roadmap |
|----------|---------|-------------|---------|---------|
| Monetization | Subscription + Billing | 0 | 2 | 0 |
| Communications | Notification Center | 0 | 1 | 0 |
| Content & docs | Form + Document Builder | 0 | 1 | 1 |
| Collaboration | Internal Chat, Wiki, PM | 0 | 1 | 2 |
| Portals | Customer, Employee, Vendor | 0 | 0 | 3 |
| Operations | Delivery, Assets | 0 | 1 | 1 |
| Governance | Audit, Permissions | 0 | 2 | 0 |
| Platform | API, Integrations, Event Bus, Developer | 1 | 3 | 1 |
| Intelligence | Analytics Studio, BI, Search | 1 | 2 | 0 |
| Infrastructure | Files, Calendar | 0 | 2 | 0 |
| Ecosystem | Industry Packs, Offline, White-label, Marketplace | 0 | 0 | 4 |

**Bottom line:** Phase 1 **Core Commerce** is largely built. Phase 2 **Operations v1** is shipped (entitlements, billing ledger, notifications, audit, policies, event bus, API keys) — see [ENTERPRISE_PLATFORM_PHASE2.md](ENTERPRISE_PLATFORM_PHASE2.md). Phase 3+ covers portals, builders, coupons, tax, and full ABAC.

---

## What is already built (Phase 1 — Core Commerce)

These are the substrate every other module must plug into.

| Capability | Status | Key code |
|------------|--------|----------|
| Multi-tenant companies | **Implemented** | `Company`, `CompanySetting`, tenant scoping on all APIs |
| WhatsApp integration | **Implemented** | `WhatsAppOnboardingService`, `ProcessIncomingWhatsAppMessage`, webhooks |
| Product catalog | **Implemented** | `Product`, `ProductController`, embeddings sync |
| Orders & payments | **Implemented** | `Order`, `OrderPaymentService`, M-Pesa/Stripe/Paystack |
| Inventory signals | **Partial** | Stock on products; agent `low_stock` events; no warehouse module |
| CRM / customers | **Partial** | `Customer` derived from chats/orders; no full CRM pipeline |
| Team (basic) | **Partial** | `TeamController`, roles `admin` / `company_owner` / `company_user` |
| Plans & subscriptions (basic) | **Partial** | `Plan`, `Subscription`, Stripe recurring; M-Pesa/Paystack manual month |
| AI commerce agent | **Implemented** | 20 tools, orchestrator, memory, proactive outreach |
| Growth engine | **Implemented** | Social posts, attribution, portfolio analytics |
| Executive AI | **Implemented** | Dashboard, approvals, morning brief, opportunities |

---

## Module registry

Each module: **vision → status → what exists → gaps → build spec (global standards)**.

---

### 18. Subscription Engine

**Vision:** Monthly, annual, usage-based, credits, seat-based, feature flags, trials, coupons, partner pricing.

| Sub-capability | Status | Evidence |
|----------------|--------|----------|
| Plans (CRUD, public list) | **Implemented** | `Plan`, `PlanController`, `PlanSeeder`, admin CRUD |
| Trials (on signup) | **Partial** | `AuthController::createDefaultTrialSubscription`, `config/subscription.php`; `trial_elapsed_action` **never executed** |
| Monthly / annual billing | **Partial** | Stripe recurring; M-Pesa/Paystack = **+1 month one-shot**, no auto-renew |
| Usage-based limits | **Partial** | `PlanLimitService`, `AiBillingService`, growth/WhatsApp limits — **hardcoded per plan slug**, not DB entitlements |
| Credits (platform) | **Roadmap** | Meta WhatsApp credit lines only (`WhatsAppCreditSharingService`) |
| Seat-based | **Partial** | Limits reported in `SubscriptionController::usage()` — **not enforced** on invite |
| Feature flags | **Partial** | Implicit gating via plan slug in `PlanLimitService`; no `FeatureFlag` model |
| Coupons / promos | **Roadmap** | No tables, APIs, or checkout integration |
| Partner / reseller pricing | **Roadmap** | No partner tiers or revenue share |

**Critical gaps**

1. No unified `SubscriptionEngine` service — logic scattered across Stripe/M-Pesa/Paystack controllers.
2. Plan `features` JSON is marketing copy, not runtime entitlements.
3. No trial expiry job, downgrade, or dunning.
4. No usage metering ledger for overage billing.

**Build spec (global standards)**

```
subscription_plans          — entitlements JSON (messages, seats, modules, flags)
subscription_entitlements   — resolved effective entitlements per company
usage_meters                — meter_key, period, consumed, limit
usage_events                — append-only usage records (idempotent)
subscription_coupons        — code, %/fixed, redemption limits, partner_id
subscription_trials         — trial state machine + scheduled transitions
partner_accounts            — reseller markup, commission, white-label scope
```

- **Security:** Coupon redemption rate-limited; partner API keys scoped to tenant subtree.
- **Scale:** Usage events async via queue; aggregate meters hourly.
- **Multi-tenant:** Every row `company_id`; platform plans global, overrides per company.

---

### 19. Billing Engine

**Vision:** Invoices, payments, refunds, tax, credits, proration, revenue reports.

| Sub-capability | Status | Evidence |
|----------------|--------|----------|
| Invoices (subscription) | **Partial** | Stripe passthrough `SubscriptionController::invoices()`; no local `invoices` table |
| Invoices (orders) | **Partial** | `order-receipt.blade.php`, signed `orders.receipt` URL |
| Payments | **Partial** | Stripe/M-Pesa/Paystack gateways; `PaymentGateway` model |
| Refunds | **Partial** | Agent `issue_order_refund` sets DB status only — **no gateway reversal** |
| Tax | **Roadmap** | No VAT/GST, Stripe Tax, or tax lines |
| Credits / wallet | **Roadmap** | No prepaid balance |
| Proration | **Roadmap** | No upgrade/downgrade math; Stripe portal only |
| Revenue reports | **Partial** | Admin `RevenueController`, order aggregates; no unified billing ledger |

**Critical gaps**

1. No single payment ledger — cannot reconcile platform revenue across gateways.
2. Refunds are status flags, not financial events.
3. No PDF invoice generation for local billing.
4. M-Pesa/Paystack subscriptions lack invoice history.

**Build spec**

```
billing_invoices            — local invoice header (subscription + one-time)
billing_invoice_lines       — line items, tax, discounts
billing_payments            — gateway, external_id, amount, status
billing_refunds             — linked to payment, gateway refund id
billing_credits             — wallet balance, credit transactions
billing_tax_rates           — jurisdiction, rate, inclusive flag
revenue_snapshots           — daily MRR/ARR/churn aggregates (materialized)
```

- **Idempotency:** All webhook handlers keyed by `external_event_id`.
- **Audit:** Every financial mutation → Audit Center (module 21).
- **Compliance:** PCI — never store raw card data; use gateway tokens only.

---

### 20. Notification Center

**Vision:** One system — email, SMS, WhatsApp, push, in-app, voice, rules, templates, scheduling.
