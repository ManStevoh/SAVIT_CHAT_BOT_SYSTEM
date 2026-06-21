---
title: Payments
parent: Customer Journeys
nav_order: 32
---

# Customer Journey: Payments

How customers pay for orders on WhatsApp after confirming their order.

## When payment is asked

After order confirmation, if **Collect payment** is enabled in company settings and at least one method is configured, the bot asks:

```
How would you like to pay?
1. M-Pesa
2. Card
3. Pay manually (if configured)
```

If only one method is enabled, bot skips the menu and proceeds directly.

## Option 1: M-Pesa (STK Push)

### Customer experience

1. Bot asks for M-Pesa phone number (or uses WhatsApp number)
2. Customer confirms or provides alternate number
3. **STK push** appears on customer's phone
4. Customer enters M-Pesa PIN
5. On success, bot sends payment confirmation + order number
6. Order marked **Paid** in business dashboard

### What business needs

- PayBill or Till number with Lipa Na M-Pesa Online passkey
- Configured in **Settings → Order Payments**

See company settings guide for PayBill vs Till setup.

## Option 2: Card (Stripe)

### Customer experience

1. Bot sends **payment link** on WhatsApp
2. Customer taps link → opens Stripe Checkout in browser
3. Enters card details on Stripe-hosted page
4. On success, redirected to thank-you page
5. Bot sends WhatsApp payment confirmation
6. Order marked **Paid** automatically

### Security

Card details never pass through SAVIT servers — handled entirely by Stripe.

## Option 3: Manual payment

### Customer experience

1. Bot sends order summary + manual instructions:
   - Bank account details
   - PayBill number and account reference
   - "Pay on delivery" instructions
2. Customer pays offline
3. Customer may reply "paid" or send proof screenshot
4. Staff marks order **Paid** manually in dashboard

## Payment failures

| Scenario | Customer sees | Business action |
|----------|---------------|-----------------|
| M-Pesa cancelled | "Payment not completed" + retry option | Order stays pending payment |
| M-Pesa timeout | Retry prompt | Check M-Pesa config |
| Card declined | Stripe error on checkout page | Customer retries or chooses M-Pesa |
| Wrong amount | N/A (amount fixed at order total) | — |

## Order receipt

After successful payment, customer may receive:

- WhatsApp confirmation with order number
- Link to signed order receipt (PDF/view in browser)

## Pay on delivery / no payment

If **Collect payment** is off:

- Bot confirms order without payment step
- Business collects payment on delivery or separately

## Subscription payments (business owner)

This guide covers **customer order payments**. Business owners pay **platform subscription** separately via Dashboard → Subscription (Stripe/M-Pesa/Paystack).

## Related configuration

- Company: [Settings → Order Payments](../company-dashboard/settings.md)
- Platform defaults: [Payment Gateways (Super Admin)](../super-admin/payment-gateways.md)
- Technical: [Payments integration](../../technical/payments.md)
