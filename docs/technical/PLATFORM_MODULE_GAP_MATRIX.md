# Platform Module Gap Matrix

Quick reference for product and engineering. Full specs: [SAVIT_ENTERPRISE_PLATFORM.md](SAVIT_ENTERPRISE_PLATFORM.md).

**Last verified:** 2026-07-11 · Phase 2 foundation shipped — see [ENTERPRISE_PLATFORM_PHASE2.md](ENTERPRISE_PLATFORM_PHASE2.md)

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Implemented (production path) |
| 🟡 | Partial (adjacent code, major gaps) |
| ⬜ | Roadmap (not built) |

## Monetization & billing

| # | Module | Status | Top gap |
|---|--------|--------|---------|
| 18 | Subscription Engine | 🟡 | **DB entitlements + usage meters + trial job** — coupons/seats UI pending |
| 19 | Billing Engine | 🟡 | **Local billing_payments ledger** — tax/proration/gateway refunds pending |

## Communications

| # | Module | Status | Top gap |
|---|--------|--------|---------|
| 20 | Notification Center | 🟡 | **Templates + dispatcher + delivery log** — SMS/push absent |

## Content & collaboration

| # | Module | Status | Top gap |
|---|--------|--------|---------|
| 5 | Form Builder | ⬜ | No dynamic forms |
| 6 | Document Builder | 🟡 | Order receipt only; no quotations/contracts/POs |
| 7 | Internal Chat | ⬜ | Chats are customer WhatsApp only |
| 8 | Company Wiki | 🟡 | FAQ + chunks; no wiki pages/videos |
| 0 | Project Management | ⬜ | No tasks/kanban |

## Portals

| # | Module | Status | Top gap |
|---|--------|--------|---------|
| 12 | Customer Portal | ⬜ | WhatsApp-only customer UX |
| 4 | Employee Portal | ⬜ | Team list only |
| — | Vendor Portal | ⬜ | Not started |

## Operations

| # | Module | Status | Top gap |
|---|--------|--------|---------|
| 6 | Delivery Management | 🟡 | Order status only; no fleet/GPS/POD |
| 8 | Asset Management | ⬜ | Not started |

## Governance

| # | Module | Status | Top gap |
|---|--------|--------|---------|
| 21 | Audit Center | 🟡 | **audit_events + AuditService wired** — full admin audit UI pending |
| 22 | Permission Engine | 🟡 | **company_policy_rules CRUD + approval ABAC** — dashboard ABAC pending |

## Platform

| # | Module | Status | Top gap |
|---|--------|--------|---------|
| 23 | API Platform | 🟡 | **API keys + v1 orders + outbound webhooks** — OpenAPI publish pending |
| 24 | Integration Hub | 🟡 | Growth connectors only |
| 25 | Analytics Studio | 🟡 | Fixed dashboards; no custom KPI builder |
| 26 | Search Engine | 🟡 | Per-entity search; no global index |
| 27 | File Management | 🟡 | Uploads only; no drive/versioning |
| 28 | Calendar | 🟡 | Working hours only |
| 29 | Event Bus | 🟡 | **domain_events outbox + fan-out job** — full platform event catalog pending |
| 30 | Developer Platform | ⬜ | No marketplace/apps |

## Ecosystem

| # | Module | Status | Top gap |
|---|--------|--------|---------|
| 31 | Industry Packs | 🟡 | Industry field + agent DNA; no pack installer |
| 32 | Offline Capability | ⬜ | Not started |
| 33 | White-Label | ⬜ | Platform branding only |
| 34 | BI Layer | 🟡 | Executive AI; no custom KPIs |
| 35 | Ecosystem | ⬜ | Architecture defined in blueprint |

## Counts

| Status | Count |
|--------|-------|
| ✅ Full module | 0 (capabilities live inside commerce stack) |
| 🟡 Partial | 22 |
| ⬜ Roadmap | 13 |

## Next implementation priority (Phase 2a–2h)

1. **Subscription Engine** — entitlements table, usage meters, trial job
2. **Billing Engine** — invoice + payment ledger
3. **Notification Center** — dispatcher + templates
## Phase 2 status (2026-07-11)

Items 1–7 below have **v1 foundations shipped**. See [ENTERPRISE_PLATFORM_PHASE2.md](ENTERPRISE_PLATFORM_PHASE2.md).

1. ~~**Subscription Engine**~~ — entitlements DB + meters + trial job ✅ v1
2. ~~**Billing Engine**~~ — billing_payments ledger ✅ v1
3. ~~**Notification Center**~~ — templates + dispatcher ✅ v1
4. ~~**Permission Engine**~~ — policy rules CRUD + approval ABAC ✅ v1
5. ~~**Audit Center**~~ — audit_events wired ✅ v1
6. ~~**Event Bus v1**~~ — domain_events + outbox ✅ v1
7. ~~**API Platform v1**~~ — API keys + webhooks ✅ v1
