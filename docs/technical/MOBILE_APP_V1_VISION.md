---
title: Mobile App V1 Vision
parent: Technical Documentation
nav_order: 25
---

# Essem Mobile Companion — V1 Vision

> Flutter company companion for Essem Chat Bot (SAVIT).  
> UI reference: [Chating PWA (Community) on Figma](https://www.figma.com/design/B83EhYqugSUcb09E0gUCpf/Chating-PWA--Community-?node-id=33-638)

## Why this app exists

Company owners and agents already run the business from the web dashboard. V1 brings the **same company workspace to mobile** so they can:

- Answer chats on the go
- Add / find customers (contacts)
- Check orders, products, FAQs, growth, and settings without opening a laptop

This is **not** a customer-facing WhatsApp replacement. Customers stay on WhatsApp; staff use Essem Mobile.

## Product positioning

| Role | Mobile V1 |
|------|-----------|
| Company owner / agent | Primary audience |
| End customer | Out of scope (WhatsApp + web widget) |
| Super admin | Out of scope for V1 |

**Codename / package:** `MOBILE_APP/` · Flutter · talks to Laravel Sanctum APIs under `/api`.

## Design direction

Inspired by the Figma community chat PWA, adapted for Essem branding:

| Token | Direction |
|-------|-----------|
| Primary | Vibrant purple (headers, CTAs, accents) |
| Surfaces | White cards on soft lavender / light canvas |
| Chat | Soft blue / purple bubbles, avatar + timestamp |
| Splash | Clean white with Essem / app mark (not generic “PWA” logo) |
| Density | Mobile-first; one job per screen |

Figma is a **visual reference**, not a pixel-perfect source of truth. Screens map to Essem domains (inbox, customers, orders, etc.).

## V1 feature map

### 1. Splash & branding
- Branded splash on cold start
- Pull `GET /api/app-branding` when available
- Fall back to Essem defaults

### 2. Auth
- Login (`POST /api/auth/login`)
- Persist Sanctum bearer token securely
- `GET /api/auth/me`, logout, basic profile

### 3. Inbox (core)
- Chat list (`GET /api/company/chats`)
- Thread + history (`GET /api/company/chats/{id}/messages`)
- Send reply (`POST /api/company/chats/{id}/messages`)
- Optional: hand-back to bot

### 4. Contacts (Add contacts)
- List customers (`GET /api/company/customers`)
- Search by name / phone
- **Add contact / start chat by phone** — requires a small new Laravel endpoint (customers today are derived from orders; create + open chat is a V1 backend gap)
- UI follows Figma: phone field, “+ Add”, existing list with “+ Add”

### 5. Admin home
- Summary cards from `GET /api/company/analytics` + `GET /api/company/customers/stats`
- Notifications (`GET /api/company/notifications`, mark read)
- Deep links into Orders / Products / FAQ / Growth / Settings

### 6. Orders
- List + detail (`GET /api/company/orders`, `GET /api/company/orders/{id}`)
- Status update (`PATCH /api/company/orders/{id}`)

### 7. Products
- List / create / update / delete via existing product APIs
- Mobile = usable catalog management, not every variant edge case on day one

### 8. FAQs
- List / create / update / delete via existing FAQ APIs

### 9. Growth (lightweight)
- Overview from `GET /api/company/growth/analytics` (+ pilot / onboarding status if useful)
- Key actions only (view posts / status) — not full intelligence console

### 10. Settings
- Profile + password
- Core company prefs already exposed to the authenticated user
- Subscription status read-only link-out if needed

## Information architecture

```
Splash → Auth gate
         └─ App shell (bottom nav)
              ├─ Home (admin summary + notifications)
              ├─ Chats (inbox)
              ├─ Contacts
              └─ More
                   ├─ Orders
                   ├─ Products
                   ├─ FAQs
                   ├─ Growth
                   └─ Settings
```

## Architecture (Flutter)

```
MOBILE_APP/
  lib/
    main.dart
    app.dart                 # MaterialApp, theme, routes
    core/                   # config, theme, http, storage, errors
    features/
      splash/
      auth/
      home/
      chats/
      contacts/
      orders/
      products/
      faqs/
      growth/
      settings/
    shared/                 # widgets, models used across features
```

| Concern | Approach |
|---------|----------|
| HTTP | `dio` + Sanctum `Authorization: Bearer` |
| State | Feature-local + simple app-wide auth/session |
| Secure token | `flutter_secure_storage` |
| Navigation | `go_router` |
| Env | `--dart-define=API_BASE_URL=...` |

## Backend work required for V1

| Item | Status |
|------|--------|
| Auth, chats, messages, orders, products, FAQs, analytics, notifications, growth read APIs | Exists |
| App branding | Exists |
| **Create customer / start chat by phone** | **Done** — `POST /api/company/chats/start` |
| Orders / Products / FAQs / Growth / Settings mobile UI | **Done** — see [Pending map](MOBILE_APP_V1_PENDING.md) |
| Push notifications (FCM) | Stretch; not blocking UI V1 |
| Real-time chat (WebSocket / polling) | Polling acceptable for V1 |

## Explicitly out of V1

- Super-admin console
- Customer consumer app
- Full Growth intelligence / Meta OAuth flows on device
- Deep product variant / image studio parity with web
- Offline-first sync

## Success criteria

1. Company user can log in on Android/iOS and stay logged in
2. Can read and reply to chats from the phone
3. Can add a contact by phone and land in a chat thread
4. Can open Home and see live analytics + notifications
5. Can manage orders, products, and FAQs at a basic level
6. Can open Growth overview and Settings
7. Visual language clearly related to the Figma chat companion (purple system, splash, contact + chat patterns)

## Build sequence

1. Scaffold Flutter app + theme + routing shell  
2. Splash + auth against Laravel  
3. Inbox (list + thread + send)  
4. Contacts + backend “add / start chat” endpoint  
5. Admin home + notifications  
6. Orders → Products → FAQs → Growth → Settings  
7. Polish (splash branding, empty states, error handling)  
8. Store-ready packaging (icons, signing) — after feature freeze  

## Related docs

- [API reference](api-reference.md) / [LARAVEL_API](../LARAVEL_API.md)
- [Company chats (user guide)](../user-guide/company-dashboard/chats.md)
- [Tech stack](tech-stack.md)
