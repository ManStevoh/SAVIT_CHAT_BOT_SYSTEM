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

| Channel | Status | Evidence |
|---------|--------|----------|
| Email | **Implemented** | `MailService` — subscription, orders, messages, expiry |
| In-app | **Implemented** | `CompanyNotification`, `CompanyInAppNotificationService`, API |
| WhatsApp (customer) | **Implemented** | Commerce channel — not owner notification hub |
| SMS | **Roadmap** | No provider integration |
| Push (web/mobile) | **Roadmap** | No FCM/OneSignal |
| Voice | **Roadmap** | Owner voice *commands* exist (Phase 5); not outbound voice alerts |
| Rules / templates | **Partial** | Blade/email strings inline; admin notify toggles **stored but unused** |
| Scheduling | **Partial** | `subscription:expiry-reminders` cron only |

**Critical gaps**

1. No `NotificationDispatcher` — channels called ad hoc from jobs/controllers.
2. No user preference matrix (per channel, per event type).
3. Admin `notify_*` flags in `PlatformSetting` never trigger sends.

**Build spec**

```
notification_templates      — channel, locale, subject/body, variables
notification_rules          — event_type → channels, conditions, throttle
notification_deliveries   — status, provider_id, read_at (in-app)
notification_preferences    — user/company channel opt-in
notification_schedules      — delayed/batch sends
```

- **Reliability:** Queue-backed; retry with exponential backoff; dead-letter table.
- **Compliance:** Opt-out per channel; SMS/WhatsApp template approval tracking.

---

### 5. Form Builder

**Vision:** Drag-and-drop forms — warranty claims, returns, surveys, registrations, job applications, service requests; conditional logic.

| Status | **Roadmap** |

**Evidence:** No `Form`, `FormField`, `FormSubmission` models. Only static React forms + Laravel `validate()`.

**Build spec**

```
forms                       — company_id, name, schema JSON, status
form_versions               — immutable published versions
form_submissions            — encrypted PII fields, source, chat_id link
form_logic                  — conditional rules engine (safe DSL, no eval)
```

- **Security:** CSRF, rate limits, CAPTCHA on public forms, field-level encryption for PII.
- **AI integration:** Agent can pre-fill from `customer_memories`; submissions → event bus.

---

### 6. Document Builder

**Vision:** Generate quotations, invoices, contracts, POs, receipts, delivery notes, certificates from reusable templates.

| Document | Status | Evidence |
|----------|--------|----------|
| Receipts | **Partial** | `order-receipt.blade.php` |
| Invoices (subscription) | **Partial** | Stripe-hosted |
| Quotations | **Roadmap** | — |
| Contracts | **Roadmap** | — |
| Purchase orders | **Roadmap** | — |
| Delivery notes | **Roadmap** | — |
| Certificates | **Roadmap** | — |

**Build spec**

```
document_templates          — type, html/docx schema, variables, locale
document_instances          — rendered PDF path, status, signers
document_workflows          — draft → sent → signed → archived
```

- **Standards:** PDF/A for archival; digital signatures (optional module); template versioning.
- **Integration:** Order paid → auto-generate invoice; agent tool `generate_quotation`.

---

### 7. Internal Chat (Slack-like)

**Vision:** Employee chat; share orders, customers, products, tasks, invoices without copying links.

| Status | **Roadmap** |

**Evidence:** `Chat` model is **customer WhatsApp only** (`customer_phone`). No channels, DMs, or entity cards.

**Build spec**

```
internal_channels           — public/private, company_id
internal_messages           — thread, mentions, attachments
internal_entity_refs        — polymorphic links to Order, Customer, Product, etc.
```

- **Real-time:** Laravel Reverb/Pusher or SSE for live updates.
- **Permissions:** Channel membership via Permission Engine (module 22).

---

### 8. Company Wiki / Knowledge Base

**Vision:** Policies, training, procedures, FAQs, videos — searchable.

| Status | **Partial** |

**Evidence:** `Faq` CRUD + dashboard; `KnowledgeChunk` embeddings; `SearchKnowledgeTool`; `KnowledgeArtifact` (AI compression). No wiki articles, versioning, or video library UI.

**Gap → build:** Extend FAQ into `wiki_pages` with categories, markdown, version history, full-text + semantic search unified in Search Engine (module 26).

---

### 0. Project Management

**Vision:** Kanban, Gantt, tasks, dependencies, milestones, time tracking, files.

| Status | **Roadmap** |

**Build spec:** `projects`, `boards`, `columns`, `tasks`, `task_dependencies`, `time_entries` — link tasks to orders/customers via entity refs.

---

### 12. Customer Portal

**Vision:** Customers log in — track orders, invoices, tickets, subscriptions, downloads, loyalty.

| Status | **Roadmap** |

**Evidence:** Customers use WhatsApp only. Signed receipt URLs are the only self-service artifact.

**Build spec:** Separate Sanctum guard `customer`; portal subdomain optional; SSO magic links via WhatsApp.

---

### 4. Employee Portal

**Vision:** Tasks, payslips, leave, training, approvals, expenses, performance.

| Status | **Roadmap** |

**Adjacent:** `TeamController` lists users. Dashboard is owner/team ops, not HR portal.

---

### Vendor Portal

**Vision:** Suppliers update inventory, upload invoices, accept POs, track payments, communicate.

| Status | **Roadmap** |

**Note:** Distinct from Composer `vendor/`. Requires `vendors` table, scoped login, PO workflow tied to Document Builder.

---

### 6. Delivery Management

**Vision:** Routes, drivers, vehicles, GPS, proof of delivery, photos, signatures, fuel, maintenance.

| Status | **Partial** |

**Evidence:** `delivery_address` on orders; `CheckDeliveryStatusTool`; `CommerceEventDetector::detectDeliveryDelays`. No fleet, routes, or POD.

---

### 8. Asset Management

**Vision:** Track laptops, printers, vehicles, furniture; maintenance schedules, assignments.

| Status | **Roadmap** |

---

### 21. Audit Center

**Vision:** Who did what, when, where, old/new value, IP, device, approval — enterprise critical.

| Status | **Partial** |

**Evidence:**

- `SystemLog` + admin logs UI
- `AgentTrustLog` — AI decisions only
- `audit_logging_enabled` in `PlatformSetting` — **toggle only, not wired**

**Build spec**

```
audit_events                — actor, action, entity, old_values, new_values, ip, user_agent
audit_retention_policies    — per entity type, GDPR export
```

- **Standards:** Immutable append-only; WORM storage option; SIEM export (JSON Lines).

---

### 22. Permission Engine

**Vision:** Fine-grained policies — e.g. Sales Manager approves discounts up to KSh 20,000; Finance refunds below KSh 50,000; Support cannot view payroll.

| Status | **Partial** |

**Evidence:** Role column on `User`; `EnsureUserIsAdmin` middleware; `PlanLimitService` plan gates; agent `tool_risk_levels` + approval queue. **No** Laravel Policies directory, no Spatie Permission, no amount-based rules.

**Build spec**

```
permissions                 — permission_key
roles                       — company-scoped role definitions
role_permissions            — many-to-many
policy_rules                — ABAC: resource, action, conditions JSON (amount_lt, department)
```

- **Integration:** Replace hardcoded checks in controllers; agent tools consult policy engine before execution.

---

### 23. API Platform

**Vision:** Everything exposed — REST, GraphQL, webhooks, SDKs.

| Status | **Partial** |

**Evidence:** ~300 lines in `routes/api.php`; Sanctum auth; **inbound** webhooks (WhatsApp, Stripe, Paystack, M-Pesa). No public API keys, OpenAPI spec, outbound webhook subscriptions, GraphQL, or SDK packages.

**Build spec:** `api_keys`, `webhook_endpoints`, `webhook_deliveries`, OpenAPI 3.1 generated from routes, rate limiting per key, scoped OAuth2 for partners.

---

### 24. Integration Hub

**Vision:** Accounting, ERP, POS, banks, payment gateways, e-commerce, calendar, cloud storage, BI.

| Status | **Partial** |

**Evidence:** `GrowthIntegrationService` (GA4, email, website); Meta OAuth; payment gateways; agent external tools (M-Pesa check, shipping API, calendar). No connector framework or marketplace.

**Build spec:** `integrations`, `integration_credentials` (encrypted), `integration_sync_logs`, pluggable `IntegrationDriver` interface per provider.

---

### 25. Analytics Studio

**Vision:** Custom dashboards, charts, KPIs, scheduled reports, drill-downs, exports.

| Status | **Partial** |

**Evidence:** Fixed dashboards — `AnalyticsController`, Growth analytics, Executive AI, Cognitive dashboard. No user-defined widgets or report builder.

**Build spec:** `analytics_dashboards`, `widgets`, `kpi_definitions`, `scheduled_reports` — query layer on read replicas / warehouse.

---

### 26. Search Engine

**Vision:** Google-like — one search bar across customers, products, orders, chats, invoices, employees, files, tasks, meetings.

| Status | **Partial** |

**Evidence:** Per-entity SQL `search` params; semantic search for FAQ/products/knowledge chunks. No global index (Meilisearch/Elasticsearch/Typesense).

**Build spec:** Unified `search_index` pipeline via event bus; company-scoped indices; highlight snippets; permission-filtered results.

---

### 27. File Management

**Vision:** Store images, videos, documents, contracts, receipts, audio; version history; permissions.

| Status | **Partial** |

**Evidence:** Product images, chat attachments (`storage/public`), CSV exports. No folder tree, versioning, or ACLs.

**Build spec:** `files`, `file_versions`, `folders`, S3-compatible storage, virus scan hook, signed URLs with TTL.

---

### 28. Calendar

**Vision:** Unified calendar — meetings, orders, deliveries, tasks, campaigns, birthdays, subscriptions, maintenance.

| Status | **Partial** |

**Evidence:** `working_hours` on `CompanySetting`; `CalendarAvailabilityService` for agent tool. No events CRUD or calendar UI/API.

**Build spec:** `calendar_events` polymorphic source; iCal feed; conflict detection; timezone per company.

---

### 29. Event Bus

**Vision:** Every action creates an event → inventory, payment, notification, analytics, webhook, automation.

| Status | **Partial** |

**Evidence:** `CommerceEventDetector` + `commerce_agent_events` — **commerce-agent domain only**, not platform-wide. Laravel queues used ad hoc.

**Build spec**

```
domain_events               — event_name, payload, company_id, correlation_id
event_subscriptions         — handler class or webhook target
event_outbox                — transactional outbox pattern for reliability
```

- **Standards:** CloudEvents 1.0 envelope; idempotent consumers; dead-letter queue.

---

### 30. Developer Platform / Marketplace

**Vision:** Apps, themes, AI skills, reports, integrations, dashboards, widgets, automation templates.

| Status | **Roadmap** |

**Adjacent:** Agent tool registry (20 tools) is an internal plugin pattern. No third-party app install, sandbox, or revenue share.

**Build spec:** `marketplace_apps`, `app_installations`, `app_permissions`, OAuth for apps, webhook subscriptions per app.

---

### 31. Industry Packs

**Vision:** Retail, pharmacy, schools, healthcare, restaurants, hotels, construction, manufacturing, agriculture, SACCOs, NGOs — modules, templates, workflows, terminology.

| Status | **Partial** |

**Evidence:** `company.industry` field; `config/agent.php` `business_dna_defaults` and `skill_modules` per industry; no pack installer.

**Build spec:** `industry_packs` manifest JSON — seeds FAQs, templates, agent prompts, default workflows on company onboarding.

---

### 32. Offline Capability

**Vision:** Offline order capture, inventory counts, local cache, sync when online.

| Status | **Roadmap** |

**Build spec:** PWA service worker + IndexedDB queue; `offline_sync_batches` API; conflict resolution (last-write-wins + manual merge for orders).

---

### 33. White-Label Platform

**Vision:** Agency logo, domain, colors, emails, resell, manage sub-customers.

| Status | **Roadmap** |

**Adjacent:** `AppBrandingController` for platform logo/name. No per-tenant domain, email SMTP override per partner, or reseller hierarchy.

---

### 34. Business Intelligence Layer

**Vision:** KPI definitions, custom metrics, branch benchmarking, scheduled executive reports, goal tracking, forecasting.

| Status | **Partial** |

**Evidence:** Executive AI, owner analytics, `BusinessHealthScore`, `ForecastService` (cognitive), growth portfolio. No user-defined KPIs or branch comparison.

---

### 35. Ecosystem

**Vision:** Business Apps + AI Services + Developer Platform + Marketplace + Integrations + Analytics + White Label + Industry Packs + Partner Network.

| Status | **Roadmap** (architecture stage) |

The AI stack (Phases 1–5) is the **AI Services** pillar. This blueprint defines the **Business Apps** and **Platform** pillars required for a full ecosystem.

---

## Cross-cutting engineering standards

All new modules must meet these **global SaaS standards** before marking **Implemented**:

### Security

| Requirement | Current state | Target |
|-------------|---------------|--------|
| Authentication | Sanctum + roles | + policies, 2FA, session revocation |
| Authorization | Role column | Permission Engine with ABAC |
| Tenant isolation | `company_id` on queries | Global scopes + policy tests per module |
| Encryption at rest | DB defaults | Credentials in `integration_credentials` encrypted |
| Audit trail | Partial | Audit Center on all mutations |
| Rate limiting | Auth routes | Per API key + per company quotas |
| Input validation | Controller validate | Form requests + shared schemas |

### Scalability

| Pattern | Application |
|---------|-------------|
| Async by default | Notifications, webhooks, search indexing, usage metering via queues |
| Idempotency | Payment webhooks, event consumers, notification sends |
| Read/write split | Analytics on aggregates; avoid N+1 in list APIs |
| Caching | Entitlements resolved once per request; Redis for meters |
| Multi-region (future) | Stateless API; object storage for files |

### Observability

| Tool | Use |
|------|-----|
| `SystemLog` | Platform errors |
| `AgentTrustLog` | AI decisions |
| `audit_events` (new) | User actions |
| `ai:health-check` | AI pipeline |
| OpenTelemetry (roadmap) | Distributed traces |

---

## Phased roadmap (recommended)

Aligned with your sequence — **foundation before portals**:

```text
Phase 1 — Core Commerce          ✅ LARGELY COMPLETE
  Multi-tenant, WhatsApp, catalog, orders, payments, CRM-lite, AI agent

Phase 2 — Operations             🟡 v1 SHIPPED (2026-07-11)
  ├── 2a Subscription Engine     ✅ entitlements DB, usage meters, trial job
  ├── 2b Billing Engine          ✅ billing_payments ledger (Stripe/M-Pesa)
  ├── 2c Notification Center     ✅ templates + dispatcher + delivery log
  ├── 2d Permission Engine       ✅ policy rules CRUD + approval ABAC
  ├── 2e Audit Center            ✅ audit_events wired to toggle
  ├── 2f Event Bus v1            ✅ domain_events outbox + fan-out
  ├── 2g File Management v1      ⬜ roadmap
  └── 2h API Platform v1         ✅ API keys + v1 orders + outbound webhooks

Phase 3 — Platform surfaces
