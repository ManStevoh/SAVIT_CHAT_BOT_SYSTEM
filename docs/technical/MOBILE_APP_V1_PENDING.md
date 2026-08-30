---
title: Mobile App V1 Pending Map
parent: Mobile App V1 Vision
nav_order: 26
---

# Mobile App — Feature Map

## Done (company companion)

| Area | Notes |
|------|-------|
| Splash + branding | `GET /app-branding` |
| Login + forgot password | Sanctum + reset email |
| Home | Analytics, notifications, mark all read, live poll ~20s |
| Chats All/Unread | Client filter + poll ~12s |
| Chat thread | Send, hand-back, reply, learning feedback, clear history |
| Contacts + device book | Customers/chats merge + phone-book sync |
| Orders tab | Top-level bottom nav |
| Products + variants + image | CRUD + variants screen |
| Analytics | Period chips, KPIs, top products |
| Subscription | Plan, usage, invoices; checkout stays on web |
| Delivery zones | CRUD |
| Dine-in tables | CRUD + copy/open QR URL |
| Bookings | Upcoming list, status, slot settings |
| Coupons | Storefront coupon CRUD |
| Campaigns | Draft + send + monthly limits |
| Team | List + invite (one-time password shown once) |
| WhatsApp status | Connection / quality; Embedded Signup on web |
| AI replies | Greeting, tone, auto-reply, learning |
| Business + payments | Profile, mode, accepted methods |
| FAQs | CRUD |
| Growth overview + posts | Create / approve / publish |
| Settings | Profile + password + workspace shortcuts |
| Platform Admin | Role `admin` — overview, health, companies |
| 5-tab nav | Home · Chats · Contacts · Orders · More (grouped) |

## Intentionally on web (not 1:1)

These stay on the web dashboard. The More menu opens them in the browser.

| Item | Why |
|------|-----|
| Card checkout / billing portal | Do not capture cards in-app |
| WhatsApp Embedded Signup | Meta SDK + cookies belong on web |
| Register | Laravel register requires reCAPTCHA |
| Executive / Cognitive / Mission Control / Marketplace | Heavy operator consoles |
| Growth OAuth + intelligence studio | Browser OAuth + large graphs |
| Full super-admin CMS | Lean admin overview only |
| Google login | Not in Laravel today |
| FCM / WebSockets | Backend has no chat broadcast; polling used |
| Certificate pinning / biometric unlock | Follow-up hardening |

## Verification

Last green (2026-08-28): Flutter analyze + model/widget tests after companion parity pass.

Run:

```bash
cd MOBILE_APP && flutter pub get && flutter analyze && flutter test
```
