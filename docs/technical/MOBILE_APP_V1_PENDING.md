---
title: Mobile App V1 Pending Map
parent: Mobile App V1 Vision
nav_order: 26
---

# Mobile App — Feature Map

## Done (company companion + mockup extras)

| Area | Notes |
|------|-------|
| Splash + branding | `GET /app-branding` |
| Login + forgot password | Sanctum + reset email |
| Home | Analytics, notifications, live poll ~20s |
| Chats All/Unread | Client filter + poll ~12s |
| Chat thread | Send, hand-back, poll ~8s |
| Contacts + device book | Customers/chats merge + phone-book sync |
| Orders tab | Top-level bottom nav |
| Products + variants + image | CRUD + variants screen |
| FAQs | CRUD |
| Growth overview + posts | Create / approve / publish |
| Settings | Profile + password |
| Platform Admin | Role `admin` — overview, health, companies |
| 5-tab nav | Home · Chats · Contacts · Orders · More |

## Still out / limited

| Item | Why |
|------|-----|
| Google login | Needs Google OAuth credentials + backend social auth (not in Laravel today) |
| True WebSocket / FCM push | Backend has no chat broadcast; polling used instead |
| Archived chats tab | API has no archived filter |
| Full super-admin parity | Lean overview only (not every admin web page) |

## Verification

Last green (2026-07-22): Flutter analyze + model/widget tests; Laravel mobile/auth/AI suites.

Follow-up fixes: orders load-more, poll pause off-tab, Instagram media guard,
platform-admin-only shell, safer JSON parsing.

Run:

```bash
cd MOBILE_APP && flutter pub get && flutter analyze && flutter test
cd LARAVEL_BACKEND && php artisan test --filter="MobileChatStartTest|MobileCompanionApiSmokeTest|AuthSecurityTest|AiFullFlowTest"
```
