---
title: Commerce Tax
parent: Home
nav_order: 42
description: Company-defined tax rates for product orders — setup, mapping, calculation, and checkout integration.
---

# Commerce Tax

**Status:** Implemented (company tax rates on product orders).

Customers (tenants) define one or more tax rates for what they sell. Rates attach to products (or a company default). Checkout, payment, invoices, and receipts use a frozen tax snapshot so historical orders do not change when rates are edited later.

This is **order/catalog tax**, not SaaS subscription billing tax.

---

## Flow (end-to-end)

```
Company enables tax
        │
        ▼
Creates tax rates (name, %, inclusive/exclusive, default flag)
        │
        ▼
Assigns tax_rate_id on products (optional → falls back to default rate)
        │
        ▼
Customer builds cart (WhatsApp agent / OrderFlow / dashboard create)
        │
        ▼
TaxCalculationService resolves rate per line + computes money
        │
        ▼
Order persisted with:
  subtotal, tax_total, total, tax_breakdown
  order_products: tax snapshot + tax_amount + line_subtotal
        │
        ▼
Payment gateways charge orders.total (grand total including tax rules)
Invoices / receipts / WhatsApp payment messages show breakdown
```

---

## Data model

| Table | Role |
|-------|------|
| `tax_rates` | Per-company rates: `name`, `code`, `rate` (%), `is_inclusive`, `is_default`, `is_active` |
| `company_settings.tax_enabled` | Master switch |
| `products.tax_rate_id` | Optional override; `null` → company default active rate |
| `orders.subtotal` | Sum of ex-tax line amounts |
| `orders.tax_total` | Sum of tax amounts |
| `orders.total` | Amount due (what gateways charge) |
| `orders.tax_breakdown` | JSON aggregate by rate name/code for display |
| `order_products.*` | Unit `price` (catalog), plus `tax_*` snapshot columns |

---

## Rate resolution (per line)

1. If `tax_enabled` is false → no tax.
2. If product has an active `tax_rate_id` → use it.
3. Else use the company’s active default rate (`is_default = true`).
4. Else → no tax.

---

## Money rules

Catalog unit price `P`, quantity `Q`, rate `R` (%).

**Tax-exclusive** (`is_inclusive = false`):

- `line_subtotal = P × Q`
- `tax_amount = round(line_subtotal × R / 100, 2)`
- `line_total = line_subtotal + tax_amount`

**Tax-inclusive** (`is_inclusive = true`):

- `line_total = P × Q` (customer pays the listed catalog price)
- `tax_amount = round(line_total × R / (100 + R), 2)`
- `line_subtotal = line_total − tax_amount`

Order:

- `subtotal = Σ line_subtotal`
- `tax_total = Σ tax_amount`
- `total = Σ line_total`

When tax is off or unresolved: `subtotal = tax_total = 0` path collapses to `total = Σ(P × Q)` with zero tax fields (backward compatible).

---

## APIs

| Method | Path | Purpose |
|--------|------|---------|
| GET/POST | `/api/company/tax-rates` | List / create |
| PUT/DELETE | `/api/company/tax-rates/{id}` | Update / delete |
| POST | `/api/company/orders/preview-totals` | Preview subtotal/tax/total for cart lines |
| PUT | `/api/company/settings` | `taxEnabled` |
| Products | `taxRateId` on create/update + in responses |
| Orders | `subtotal`, `taxTotal`, `taxBreakdown`, line tax fields |

Only one default rate per company is enforced (setting a new default clears others).

---

## Plug-in points (code)

| Location | Responsibility |
|----------|----------------|
| `App\Services\Orders\TaxCalculationService` | Resolve rate + compute line/order totals |
| `OrderFlowService::formatDraftSummary` / `createOrderFromDraft` / `formatOrderMoneySummary` | Cart display, persist, WhatsApp confirmations |
| `OrderController::store` | Dashboard/API order create + WA invoice copy |
| `OrderInvoiceService`, `order-receipt.blade.php`, `OrderPaymentDetailsService` | Customer-facing breakdown |
| Dashboard `/dashboard/taxes` + product form tax select | Web tenant setup UI |
| Mobile More → Taxes + product tax picker | Flutter tenant setup UI |

---

## Mobile

- **More → Taxes** — enable tax + CRUD rates (`/more/taxes`)
- **Product form** — optional tax rate override
- **Order detail** — subtotal / tax / total when `taxTotal > 0`

---

## Out of scope (intentionally)

- Jurisdiction / address-based tax (shipping destination rules)
- Multi-rate stacking on a single product line
- Stripe Tax / external tax engines
- SaaS subscription invoice tax (`billing_tax_rates` roadmap remains separate)
