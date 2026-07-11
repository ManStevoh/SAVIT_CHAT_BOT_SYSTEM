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
