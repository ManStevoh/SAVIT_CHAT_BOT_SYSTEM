# Classic Global Storefront — Features 1–20

**Product:** RelayIQ public storefront (`/s/{store_slug}`)  
**Doc status:** Living specification — implementation tracked below  
**Last updated:** 2026-07-28  
**Related:** Take App parity commerce (`2026_07_28_080000_add_takeapp_parity_commerce_features`)

This document defines the **classic global storefront** upgrades (search through theme builder). Each feature includes: goal, UX, technical surface, acceptance criteria, and implementation status.

---

## Current baseline (already shipped)

| Capability | Route / surface |
|---|---|
| Catalog grid | `GET /s/{slug}` |
| Product detail | `GET /s/{slug}/p/{product}` |
| Cart | `GET/POST /s/{slug}/cart` |
| Checkout (delivery / pickup / dine-in) | `GET/POST /s/{slug}/checkout` |
| Confirmation | `GET /s/{slug}/order/{pay_token}` |
| Pay / invoice | `GET /pay/{token}`, `GET /invoice/{token}` |
| Link-in-bio | `GET /b/{slug}` |
| Dine-in QR | `GET /t/{qrToken}` |
| Tax + delivery fees + COD/bank | Company settings + `StorefrontService` |
| Growth `?ref=` attribution | Store + bio |

---

## Feature matrix

| # | Feature | Priority | Status |
|---|---|---|---|
| 1 | Search + sort + filters | P0 | Shipped |
| 2 | Checkout completeness | P0 | Shipped |
| 3 | Stock & sold-out UX | P0 | Shipped |
| 4 | SEO (meta, OG, sitemap, slugs) | P0 | Shipped |
| 5 | Guest order tracking | P0 | Shipped |
| 6 | WhatsApp CTAs | P0 | Shipped |
| 7 | Coupons / discounts | P1 | Shipped |
| 8 | Compare-at / sale badges | P1 | Shipped |
| 9 | Image gallery polish | P1 | Shipped |
| 10 | Related / upsell | P1 | Shipped |
| 11 | Branding theme | P1 | Shipped |
| 12 | Custom domain | P1 | Shipped |
| 13 | Abandoned cart recovery | P1 | Shipped |
| 14 | Storefront analytics | P1 | Shipped |
| 15 | Customer accounts / addresses | P2 | Shipped |
| 16 | Reviews & ratings | P2 | Shipped |
| 17 | Wishlist / share | P2 | Shipped |
| 18 | Multi-language / currency display | P2 | Shipped |
| 19 | Gift notes, subscriptions, bundles | P2 | Shipped |
| 20 | Theme sections / page builder v1 | P2 | Shipped |

---

## 1. Search + sort + filters

**Goal:** Shoppers find products fast without scrolling a flat list.

**UX**
- Search box on catalog (name + description)
- Sort: featured, price low→high, high→low, newest, name A–Z
- Filters: category, in-stock only, price min/max, product type

**Technical**
- Query params on `GET /s/{slug}`: `q`, `sort`, `category`, `in_stock`, `min_price`, `max_price`, `type`
- `StorefrontService::catalogFiltered(Company, array $filters)`
- Keep client chips for categories; server-side filter is source of truth

**Acceptance**
- Searching `latte` returns matching products only
- `sort=price_asc` orders correctly
- Empty result state with clear CTA

---

## 2. Checkout completeness

**Goal:** Checkout collects everything a classic shop needs before payment.

**UX fields**
- Customer name (required)
- Phone (required, E.164-friendly validation)
- Email (optional but recommended)
- Order notes
- Gift message (see #19)
- Schedule / preferred time
- Tip (optional amount)
- Live delivery fee preview when address changes
- Coupon code field (see #7)

**Technical**
- Extend `checkoutStore` validation + `orders` columns: `customer_email`, `order_notes`, `gift_message`, `tip_amount`, `coupon_code`, `discount_total`
- `POST /s/{slug}/checkout/quote` returns delivery fee + totals without placing order

**Acceptance**
- Invalid phone rejected with message
- Delivery fee updates after address entered
- Notes appear on merchant order detail / invoice

---

## 3. Stock & sold-out UX

**Goal:** Never sell inventory you don’t have.

**UX**
- Sold-out badge on cards
- Disable Add to cart when OOS
- Quantity stepper capped at available stock
- Soft warning when stock low (≤3)

**Technical**
- Respect `track_inventory` + `stock` / variant stock in `addToCart` / `placeOrder`
- Throw friendly `RuntimeException` if oversold

**Acceptance**
- OOS product cannot be added
- Cart cannot checkout with line qty > stock

---

## 4. SEO basics

**Goal:** Products discoverable in Google / social shares.

**Deliverables**
- `products.slug`, `meta_title`, `meta_description`
- Pretty URL: `/s/{store}/p/{productSlug}` (id still accepted)
- Per-page `<Head>` title/description/OG image (Inertia)
- Sitemap includes enabled storefronts + active products
- `robots.txt` continues allowing `/s/` and `/b/`

**Acceptance**
- View-source shows unique title per product
- `/sitemap.xml` lists store home + product URLs

---

## 5. Guest order tracking

**Goal:** “Where is my order?” without a dashboard login.

**UX**
- `GET /s/{slug}/track` form: phone + order number
- Result: status, payment status, items, pay link if unpaid

**Technical**
- `PublicStorefrontController::track` / `trackLookup`
- Match `company_id` + normalized phone + `order_number`

**Acceptance**
- Correct combo returns order; wrong combo returns generic not-found (no leak)

---

## 6. WhatsApp CTAs

**Goal:** Convert browsers into WhatsApp conversations (RelayIQ edge).

**UX**
- Sticky “Chat on WhatsApp” on catalog / PDP / cart
- Prefill: `Hi, I'm interested in {product}` or cart summary
- Uses company `whatsapp_number`

**Technical**
- `https://wa.me/{digits}?text=...`
- Exposed in `companyPayload.whatsappUrl` / helpers

**Acceptance**
- CTA hidden if no WhatsApp number configured
- Prefill includes product name on PDP

---

## 7. Coupons / discounts

**Goal:** Merchants run promo codes at checkout.

**Technical**
- Table `storefront_coupons`: `company_id`, `code`, `type` (percent|fixed), `value`, `min_order`, `max_redemptions`, `redeemed_count`, `starts_at`, `ends_at`, `is_active`
- Apply in `placeOrder` + quote endpoint
- API CRUD under `/api/company/storefront-coupons`

**Acceptance**
- Valid code reduces total; expired/invalid rejected

---

## 8. Compare-at / sale badges

**Goal:** Classic strike-through pricing.

**Technical**
- `products.compare_at_price` nullable
- Serialize `compareAtPrice`, `onSale`, `discountPercent`
- Badge on catalog + PDP

**Acceptance**
- When `compare_at_price > price`, UI shows strike-through + “Sale”

---

## 9. Image gallery polish

**Goal:** Multi-image browsing on PDP.

**UX**
- Main image + thumbnails
- Click thumb to swap
- Lazy loading; alt text from `product_images.alt_text`

**Acceptance**
- Products with 2+ images show gallery; single image still works

---

## 10. Related / upsell

**Goal:** Increase AOV with “You may also like”.

**Technical**
- Use existing `ProductRelationship` in `serializeProduct` / PDP props
- Fallback: same category products (limit 4)

**Acceptance**
- Related section renders when relationships or category peers exist

---

## 11. Branding theme

**Goal:** Store feels on-brand without a full theme builder.

**Technical** (on `companies` or settings JSON `storefront_theme`)
- `primary_color`, `accent_color`, `banner_url`, `banner_headline`, `footer_text`, `announcement_bar`
- Applied as CSS variables on store layout

**Acceptance**
- Changing primary color updates buttons/header accent on public store

---

## 12. Custom domain

**Goal:** `shop.brand.com` serves the storefront.

**Technical**
- `companies.custom_domain`, `custom_domain_verified_at`
- Middleware resolves host → company when not app host
- Dashboard instructions + DNS TXT/CNAME guidance
- Verification endpoint for merchants

**Acceptance**
- Verified custom domain serves `/` as that company’s catalog

---

## 13. Abandoned cart recovery

**Goal:** Recover carts left after phone/email captured.

**Technical**
- Mark `storefront_sessions` with `customer_phone`/`email`, `last_activity_at`, `recovered_at`
- Job hourly: sessions with items, phone present, inactive ≥1h, not recovered → WhatsApp nudge with cart URL
- Setting: `abandoned_cart_recovery_enabled`

**Acceptance**
- Abandoned session with phone receives WA message containing store cart link

---

## 14. Storefront analytics

**Goal:** Merchants see funnel metrics.

**Technical**
- Table `storefront_events`: `company_id`, `session_token`, `event` (view_catalog|view_product|add_to_cart|begin_checkout|purchase), `product_id`, `meta` JSON
- Dashboard widget or `/api/company/storefront/analytics`
- Fire events from public controller

**Acceptance**
- Placing an order increments purchase count for the company

---

## 15. Customer accounts / saved addresses

**Goal:** Returning shoppers checkout faster.

**Technical (v1 light)**
- Table `storefront_customers`: phone/email as identity, magic-link or OTP later
- Table `storefront_addresses`: label, line, city, notes
- Checkout can load last address by phone lookup (no full auth required in v1)

**Acceptance**
- Entering a known phone suggests last delivery address

---

## 16. Reviews & ratings

**Goal:** Social proof on PDP.

**Technical**
- Table `product_reviews`: `product_id`, `company_id`, `author_name`, `rating` 1–5, `body`, `is_approved`, `order_id` nullable
- Public list approved reviews; merchant approve API
- Average rating on serialize

**Acceptance**
- Approved review shows on PDP; unapproved hidden

---

## 17. Wishlist / share

**Goal:** Save for later + viral share links.

**Technical**
- Wishlist in `storefront_sessions.wishlist` JSON (product ids) or cookie
- Share button copies product URL + optional WhatsApp share
- `POST /s/{slug}/wishlist/toggle`

**Acceptance**
- Toggle persists for session; share copies absolute product URL

---

## 18. Multi-language / currency display

**Goal:** Global shoppers see familiar formats.

**Technical**
- Locale query `?lang=` stored on session; catalog strings stay merchant language in v1, UI chrome translated for en/sw/fr stubs
- Currency already via `CompanySetting` + `MoneyFormatter` — expose currency switcher only if merchant enables `storefront_alt_currencies` JSON (display conversion rate table; charge still in base currency)

**Acceptance**
- Money formatting respects company separators; optional lang switches chrome labels

---

## 19. Gift notes, subscriptions, bundles

**Goal:** Gift and recurring commerce patterns.

**Technical**
- Gift note: `orders.gift_message` (checkout field)
- Bundles: product type `bundle` + `product_bundle_items` (parent → child product_id, qty) expanded at order create
- Subscriptions (v1): flag `products.is_subscription` + `subscription_interval` (week|month); order line stores interval; fulfillment note for merchant (full recurring billing later)

**Acceptance**
- Gift message on invoice; bundle expands to component lines; subscription products labeled on PDP

---

## 20. Theme sections / page builder v1

**Goal:** Home page composition beyond a flat grid.

**Technical**
- `companies.storefront_sections` JSON array:
  - `{ type: 'hero', headline, subhead, image, cta_label, cta_href }`
  - `{ type: 'announcement', text }`
  - `{ type: 'featured_products', product_ids: [] }`
  - `{ type: 'rich_text', html }`
  - `{ type: 'catalog' }` (default grid)
- Dashboard editor: reorder/add/remove simple sections
- Public store renders sections in order

**Acceptance**
- Merchant can add hero + catalog; public home shows hero above products

---

## Schema additions (migration)

Migration: `2026_07_28_120000_add_classic_storefront_features.php`

**products:** `slug`, `meta_title`, `meta_description`, `compare_at_price`, `is_subscription`, `subscription_interval`, `product_type` already exists (extend usage for `bundle`)

**orders:** `customer_email`, `order_notes`, `gift_message`, `tip_amount`, `discount_total`, `coupon_code`, `coupon_id`

**companies:** `custom_domain`, `custom_domain_verified_at`, `storefront_theme` (JSON), `storefront_sections` (JSON)

**company_settings:** `abandoned_cart_recovery_enabled`, `storefront_alt_currencies` (JSON), `storefront_default_locale`

**New tables:**
- `storefront_coupons`
- `storefront_events`
- `storefront_customers`
- `storefront_addresses`
- `product_reviews`
- `product_bundle_items`

**storefront_sessions:** `customer_email`, `last_activity_at`, `abandoned_notified_at`, `wishlist` (JSON)

---

## Public routes (additions)

| Method | Path | Purpose |
|---|---|---|
| GET | `/s/{slug}` | Catalog with filters |
| GET | `/s/{slug}/p/{productSlug}` | PDP by slug or id |
| POST | `/s/{slug}/checkout/quote` | Totals preview |
| GET/POST | `/s/{slug}/track` | Order tracking |
| POST | `/s/{slug}/wishlist/toggle` | Wishlist |
| GET | `/` on custom domain | Store home |
| GET | `/api/company/storefront-coupons` | Coupon CRUD |
| GET | `/api/company/storefront/analytics` | Funnel stats |
| GET/POST | `/api/company/product-reviews` | Moderate reviews |

---

## Dashboard surfaces

| Page | Features |
|---|---|
| `/dashboard/storefront` | Theme, sections, domain, WA CTA preview, abandoned cart toggle |
| `/dashboard/delivery` | Zones (existing) |
| `/dashboard/dine-in` | Tables (existing) |
| Products form | slug, meta, compare-at, subscription/bundle flags |
| New coupons section on storefront page | Promo codes |
| Reviews moderation | Approve/reject |

---

## Tests

- `tests/Feature/ClassicStorefrontFeaturesTest.php` — search filter, stock block, SEO slug route, track order, coupon apply, sale badge serialize, related products, analytics event, review visibility, wishlist toggle, checkout quote, gift message persistence, sitemap, coupon API

## Implementation map (shipped)

| Area | Location |
|---|---|
| Spec (this doc) | `docs/technical/STOREFRONT_CLASSIC_FEATURES.md` |
| Migration | `database/migrations/2026_07_28_120000_add_classic_storefront_features.php` |
| Core service | `app/Services/Storefront/StorefrontService.php` |
| Abandoned cart | `app/Services/Storefront/AbandonedCartRecoveryService.php` + `app/Jobs/Storefront/ProcessAbandonedCartJob` (hourly) |
| Custom domain | `app/Http/Middleware/ResolveStorefrontDomain.php` |
| Public UI | `resources/js/Pages/store/{page,product,cart,checkout,track,confirmation}.tsx` |
| Merchant APIs | `/api/company/storefront-coupons`, `/api/company/storefront/analytics`, `/api/company/product-reviews` |
| Sitemap | `CmsSeoService::sitemapEntries()` includes `/s/{slug}` + PDPs |

---

## Positioning

RelayIQ storefront is **not** a Shopify clone. It is a **classic shop UX** that converts into WhatsApp sales, payments, and Growth attribution. Features 1–20 close the “looks unfinished vs Take App / Shopify” gap while keeping chat as the native close.

---

## Changelog

| Date | Notes |
|---|---|
| 2026-07-28 | Spec created; implementation of 1–20 started |
| 2026-07-28 | Features 1–20 shipped: schema, StorefrontService, public routes, dashboard APIs, sitemap, abandoned-cart job, `ClassicStorefrontFeaturesTest` |
