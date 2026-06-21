---
title: Customers
parent: Company Dashboard
nav_order: 14
---

# Customers

**URL:** `/dashboard/customers`

View and manage customers created automatically from WhatsApp conversations.

## How customers are created

When someone messages your WhatsApp business number:

1. System creates or updates a **customer record** keyed by phone number
2. A **chat** is linked to that customer
3. Name is updated if WhatsApp profile provides it

No manual registration is required from the customer side.

## Customer list

| Column | Description |
|--------|-------------|
| Phone | WhatsApp number (primary identifier) |
| Name | From WhatsApp profile or order data |
| Last seen | Last message timestamp |
| Total orders | Order count |
| Total spent | Revenue from paid orders |
| Status | New vs repeat customer |

## Customer stats

The stats endpoint powers dashboard widgets:

- Total customers
- New customers this period
- Repeat customer rate
- Top customers by order value

## Actions

From a customer row:

- **View chat** — open linked conversation
- **View orders** — filter orders by customer phone

## Segmentation use cases

| Segment | Action |
|---------|--------|
| Repeat buyers | Priority support, loyalty offers via manual message |
| New customers | Welcome follow-up from Chats |
| High value | Growth Engine retargeting content |

## Privacy

Customer phone numbers are stored for order fulfillment and chat history. Handle per your privacy policy and local regulations (GDPR, etc.).
