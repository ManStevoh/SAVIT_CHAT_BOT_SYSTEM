---
title: Products
parent: Company Dashboard
nav_order: 13
---

# Products & Catalog

**URL:** `/dashboard/products`

Manage the product catalog the WhatsApp bot uses when customers ask for prices, catalog, or place orders.

## Product fields

| Field | Required | Used by bot for |
|-------|----------|-----------------|
| Name | Yes | Catalog listings, order matching |
| Price | Yes | Price replies (never AI-invented) |
| Description | Optional | AI context, detailed replies |
| Category | Optional | Grouping in catalog |
| Availability | Yes | In stock / out of stock filtering |
| Image | Optional | Rich catalog responses |

## Variants

Products can have **variants** (e.g. size, color):

1. Open a product
2. Add variant with name, price adjustment, SKU
3. Set variant-specific availability and images

The bot presents variants during order flow when applicable.

## Images

Upload product or variant images:

- Supported formats: JPEG, PNG, WebP
- Images appear in catalog replies when customer requests products

## Bot behavior with products

| Customer message | Bot action |
|------------------|------------|
| "catalog", "prices", "menu" | Sends formatted product list from DB |
| "order [product name]" | Starts order flow for matched product |
| Product number from menu | Selects item by list position |

**Important:** Prices always come from your catalog — the AI is instructed never to invent prices.

## Bulk import

Import products from CSV:

1. Go to [Import & Export](data-import-export.md)
2. Download sample CSV format
3. Upload filled CSV via dashboard or API

## Deleting products

Deleted products are removed from future catalog replies. Existing orders retain historical line item data.
