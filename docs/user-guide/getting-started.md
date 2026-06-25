---
title: Getting Started
parent: User Guide
nav_order: 2
---

# Getting Started (Company Owner)

This guide walks a new business owner from registration to receiving their first automated WhatsApp reply.

## Step 1: Visit the platform

Open the live application:

**[https://essem-chat-bot-system.vercel.app](https://essem-chat-bot-system.vercel.app)**

Browse the landing page for features and pricing. Plans are loaded dynamically from the platform.

## Step 2: Create an account

1. Click **Get Started** or **Register**.
2. Fill in:
   - **Your name**
   - **Email address**
   - **Password** (minimum requirements shown on form)
   - **Company name** (your business name)
   - **Business category** (e.g. restaurant, salon, retail)
3. Submit the registration form.

Registration creates both a **user account** (company owner role) and a **company** (tenant) in one step.

## Step 3: Verify your email

1. Check your inbox for a verification email from the platform.
2. Click the verification link.
3. If you did not receive it, use **Resend verification** on the login page.

You must verify your email before full access is granted.

## Step 4: Log in

1. Go to **Login**.
2. Enter email and password.
3. You are redirected to the **Company Dashboard**.

## Step 5: Start your subscription

1. Navigate to **Dashboard → Subscription**.
2. Review available plans (Starter, Growth, Enterprise).
3. Choose a plan and complete checkout:
   - **Stripe** — card payment in browser
   - **M-Pesa** — STK push to your phone (if enabled)
   - **Paystack** — alternative gateway (if enabled)

A **14-day free trial** applies to Starter and Growth plans. Your bot and dashboard features require an **active subscription**.

## Step 6: Connect WhatsApp

1. Go to **Dashboard → Settings → WhatsApp** tab.
2. Choose one of:
   - **Manual connect** — enter Phone Number ID and Access Token from Meta
   - **Embedded Signup** — guided Meta OAuth flow (if configured by platform admin)
3. Save and confirm **Connected** status.

See [WhatsApp Connection](company-dashboard/whatsapp.md) for detailed Meta setup.

## Step 7: Configure your bot

1. **Settings → AI** — set greeting message, AI tone, enable auto-reply
2. **Products** — add your catalog (name, price, description, images)
3. **FAQs** — add common questions and answers
4. **Settings → Business** — working hours, delivery info, away message

Optionally import products and FAQs via CSV: [Import & Export](company-dashboard/data-import-export.md).

## Step 8: Test from WhatsApp

1. From a personal WhatsApp account, message your **business number**.
2. Send "Hi" — you should receive the greeting.
3. Try keywords: "catalog", "price", "order".
4. Check **Dashboard → Chats** to see the conversation appear.

If no reply arrives, see [WhatsApp Connection troubleshooting](company-dashboard/whatsapp.md#troubleshooting).

## Step 9: Explore the dashboard

| Next step | Section |
|-----------|---------|
| Manage live conversations | [Chats](company-dashboard/chats.md) |
| Track sales | [Orders](company-dashboard/orders.md) |
| Enable customer payments | [Settings → Order Payments](company-dashboard/settings.md) |
| View performance | [Analytics](company-dashboard/analytics.md) |
| Grow via social media | [Growth Engine](company-dashboard/growth-engine.md) |

## Password reset

If you forget your password:

1. Go to **Forgot Password**.
2. Enter your email.
3. Click the reset link in the email.
4. Set a new password on the **Reset Password** page.
