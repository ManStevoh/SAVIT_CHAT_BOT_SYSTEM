---
title: Placing Orders
parent: Customer Journeys
nav_order: 31
---

# Customer Journey: Placing Orders

Step-by-step order flow on WhatsApp.

## Triggering an order

Customer can start an order by:

- Replying **order** or **2** (if menu configured)
- Saying "I want [product name]"
- Selecting a product number from catalog list

## Order conversation steps

```
1. Product selection
      ↓
2. Quantity
      ↓
3. Delivery address / location
      ↓
4. Order summary & confirmation
      ↓
5. Payment (if enabled) → see Payments guide
      ↓
6. Confirmation message with order number
```

### Step 1: Product selection

Bot presents catalog or confirms matched product. Customer replies with:

- Product name
- Number from list ("1", "2")
- Variant choice if product has variants

### Step 2: Quantity

Bot asks: "How many would you like?"

Customer replies with number.

### Step 3: Delivery details

Bot asks for delivery address. Customer can:

- Type address as text
- Share WhatsApp location pin

### Step 4: Confirmation

Bot sends summary:

```
Order summary:
- 2x Product Name @ KES 500 = KES 1000
- Delivery: 123 Main St
Total: KES 1000

Reply YES to confirm or NO to cancel
```

Customer replies **YES** to confirm.

### Step 5: Payment (optional)

If payment collection enabled, flow continues to [Payments](payments.md).

If disabled, bot sends order confirmation with order number.

### Step 6: Confirmation

```
✅ Order confirmed!
Order #ORD-12345
We'll prepare it and contact you for delivery.
```

## Order status updates

Business staff update status in dashboard. Optional WhatsApp notifications may be sent on status changes (depending on configuration).

## Cancelling mid-flow

Customer can reply **cancel** or **no** at confirmation step to abort.

## Multiple items

Some flows support adding multiple products in one order. Customer may be asked "Add another item?" after first product.

## Conversation state

Order progress is stored in database. If customer stops mid-flow and returns later, bot may resume or restart depending on timeout settings.

## Manual orders

Orders placed by staff in dashboard appear the same in order list but without bot conversation flow.

## Business view

See [Orders dashboard](../company-dashboard/orders.md) for fulfillment workflow.
