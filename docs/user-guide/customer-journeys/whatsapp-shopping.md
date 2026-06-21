---
title: WhatsApp Shopping
parent: Customer Journeys
nav_order: 30
---

# Customer Journey: WhatsApp Shopping

This guide describes the **end customer experience** — someone messaging your business on WhatsApp. Customers never log into the SAVIT dashboard.

## Starting a conversation

1. Customer saves your **WhatsApp Business number** (from website, social media, attribution link, or QR code)
2. Opens WhatsApp and sends a message (typically "Hi", "Hello", or a question)

## First message — greeting

If auto-reply is enabled and subscription is active:

- Customer receives your configured **greeting message**
- May include quick menu options (e.g. "Reply 1 for catalog, 2 to order, 3 for support")

## Common interactions

| Customer sends | Bot responds with |
|----------------|-----------------|
| Hi / Hello | Greeting message |
| catalog / menu / prices | Product list with names and prices |
| FAQ topic or keyword | Matching FAQ answer |
| location / hours | Business address or working hours from settings |
| order / I want to buy | Starts [order flow](placing-orders.md) |
| agent / human / support | Escalates to human — bot pauses |
| General question | AI reply using your products/FAQs as context |

## Outside working hours

Customer may receive **away message** with your hours and option to leave a message or order.

## Media messages

Customers can send:

- **Images** — stored in chat; agent can view in dashboard
- **Documents** — stored for agent review
- **Location** — useful for delivery during order flow
- **Audio** — stored; bot typically asks for text clarification

## Growth Engine entry

If customer clicks an attribution link (`/g/{slug}`) before messaging:

1. Click is tracked
2. WhatsApp opens with pre-filled message containing `ref:{slug}`
3. Chat is linked to the social post
4. Subsequent orders attribute revenue to that post

## Human agent takeover

When escalated or when staff replies from dashboard:

- Customer sees messages from your business number as normal WhatsApp messages
- Bot does not interfere until staff clicks "hand back to bot"

## Service unavailable

If business subscription expired:

- Customer receives short unavailable message
- No catalog, orders, or AI replies until business renews

## Privacy from customer perspective

- Conversation is standard WhatsApp — end-to-end encrypted by WhatsApp
- Payment via M-Pesa uses Safaricom STK on their phone
- Card payments open Stripe-hosted checkout in browser
