---
title: Orders
parent: Company Dashboard
nav_order: 12
---

# Orders

**URL:** `/dashboard/orders`

Manage all orders placed through WhatsApp bot flows or created manually from the dashboard.

## Order list

Columns typically include:

| Field | Description |
|-------|-------------|
| Order number | Unique system-generated ID |
| Customer | Phone / name |
| Total amount | Order value |
| Order status | pending → confirmed → dispatched → delivered / cancelled |
| Payment status | pending / paid / refunded |
| Date | Created timestamp |
| Source | WhatsApp bot, manual, or Growth attribution |

Filter and search by status, date, or customer.

## Order detail

Click an order to view:

- Line items (products, variants, quantities, prices)
- Delivery address / notes collected by bot
- Payment method chosen (M-Pesa, card, manual)
- Payment link or STK status
- Chat link (conversation that created the order)
- Attribution link (if from Growth Engine post)

## Updating order status

Staff can manually update:

1. **Order status** — move through fulfillment pipeline
2. **Payment status** — mark Paid, Pending, or Refunded for reconciliation

Use manual payment override when customer paid offline (bank transfer, cash on delivery) or when webhook confirmation failed.

## Creating orders manually

Click **New Order** to create an order from the dashboard (walk-in or phone orders):

1. Select customer or enter phone
2. Add products and quantities
3. Set delivery details
4. Save

## Order receipt

Paid orders generate a signed receipt URL sent to customers. Receipts are accessible at `/order/{id}/receipt` on the backend domain.

## Notifications

New orders trigger:

- In-app dashboard notification
- Email to company (if configured)

## Related customer journey

See [Placing Orders](../customer-journeys/placing-orders.md) and [Payments](../customer-journeys/payments.md) for the WhatsApp-side flow.
