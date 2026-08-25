# RelayIQ Mobile (`MOBILE_APP`)

Flutter company companion for RelayIQ (SAVIT).

Vision & scope: [docs/technical/MOBILE_APP_V1_VISION.md](../docs/technical/MOBILE_APP_V1_VISION.md)

## Prerequisites

- Flutter SDK (3.22+ recommended)
- Running Laravel API (`LARAVEL_BACKEND`)

## Run

```bash
cd MOBILE_APP
flutter pub get

# Android emulator → host machine loopback is 10.0.2.2
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080/api

# iOS simulator / desktop
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8080/api
```

Demo login on the web API:

| Role | Email | Password |
|------|-------|----------|
| Company | demo1@company.local | password |

> Shell login uses Laravel Sanctum (`POST /api/auth/login`). Start the API first.

## V1 navigation

- **Home** — analytics + notifications (auto-refresh)
- **Chats** — All/Unread tabs, thread, send, hand-back (auto-refresh)
- **Contacts** — directory + add + device phone-book sync
- **Orders** — list, detail, status update
- **More** — Products (+ variants), FAQs, Growth (create/approve/publish), Settings, Platform Admin (admin role)

Login includes **Forgot password**. Splash/login use `GET /api/app-branding`.

Status map: [docs/technical/MOBILE_APP_V1_PENDING.md](../docs/technical/MOBILE_APP_V1_PENDING.md)

## Design reference

[Chating PWA (Community) — Figma](https://www.figma.com/design/B83EhYqugSUcb09E0gUCpf/Chating-PWA--Community-?node-id=33-638)
