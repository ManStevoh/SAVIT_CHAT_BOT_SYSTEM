---
title: Tech Stack
parent: Technical Documentation
nav_order: 2
---

# Tech Stack

## Frontend (`LARAVEL_BACKEND/resources/js/`)

The UI is a **unified Laravel + Inertia.js + React** application. All screens live in `LARAVEL_BACKEND/` — no separate Next.js deploy.

| Category | Technology | Version |
|----------|------------|---------|
| Server framework | Laravel + Inertia.js | 12 / 2.x |
| UI library | React | 19 |
| Language | TypeScript | 5.7 |
| Build | Vite | 7 |
| Styling | Tailwind CSS | 4 |
| Components | shadcn/ui (Radix UI) | — |
| Icons | lucide-react | — |
| Data fetching | SWR (same-origin `/api/*`) | 2.x |
| Forms | react-hook-form + zod | — |
| Charts | recharts | — |
| E2E tests | Playwright | 1.60+ |
| Package manager | npm | — |

### Frontend config notes

- `vite.config.js`: React plugin, Tailwind, path aliases for `@/` and Next.js shims
- `shims/next-link.tsx` and `shims/next-navigation.ts`: Inertia adapters for migrated pages
- Legacy Next.js app at repo root is **deprecated**

## Mobile companion (`MOBILE_APP/`)

Flutter app for company owners/agents. Vision and scope: [Mobile App V1 Vision](MOBILE_APP_V1_VISION.md).

| Category | Technology |
|----------|------------|
| Framework | Flutter / Dart |
| Auth | Laravel Sanctum bearer tokens |
| HTTP | dio |
| Navigation | go_router |
| Secure storage | flutter_secure_storage |
| API base | `LARAVEL_BACKEND` `/api/*` |

## Backend (`LARAVEL_BACKEND/`)

| Category | Technology | Version |
|----------|------------|---------|
| Framework | Laravel | 12 |
| Language | PHP | 8.2+ |
| Auth | Laravel Sanctum | Bearer tokens |
| Queue | Database driver | default |
| Scheduler | Laravel Schedule | bootstrap/app.php |
| CSV | league/csv | Import/export |
| Asset build | Vite + React + Inertia | Production UI in `public/build/` |
| Testing | PHPUnit | Feature + Unit |
| Code style | Laravel Pint | — |
| Local dev | Laravel Sail | Optional Docker |

## Database

| Environment | Driver |
|-------------|--------|
| Local dev | SQLite (`database/database.sqlite`) |
| Production | MySQL/MariaDB (recommended on cPanel) |

Session, cache, and queue use database tables by default.

## External APIs

| Service | Purpose | API version |
|---------|---------|-------------|
| Meta WhatsApp Cloud API | Send/receive messages | Graph v21.0 |
| Meta Graph API | Growth Engine social/ads | Graph v21.0 |
| OpenAI | AI replies, Growth content | gpt-4o-mini default |
| Stripe | Subscriptions + order checkout | Latest API |
| Safaricom Daraja | M-Pesa STK push | v1 |
| Paystack | Alternative payments | v1 |
| LinkedIn / TikTok / X OAuth | Growth platform connect | Platform-specific |

## Infrastructure (production)

| Component | Provider |
|-----------|----------|
| Application hosting | cPanel VPS (single Laravel app — UI + API) |
| DNS / SSL | Provider-managed |
| Email | SMTP (configurable in admin) |

## Testing stack

| Layer | Tool | Location |
|-------|------|----------|
| Backend unit/feature | PHPUnit | `LARAVEL_BACKEND/tests/` (71 tests) |
| Full UI E2E | Playwright | `LARAVEL_BACKEND/e2e/` (14 tests) |
| TypeScript | `tsc --noEmit` | `npm run typecheck` |

Run E2E locally (PHP server on 8080 required):

```bash
cd LARAVEL_BACKEND
php -S 127.0.0.1:8080 -t public   # or reuse existing server
npm run test:e2e                  # all 14 tests
npm run test:e2e:journey          # full admin/company/public journeys only
```

## Version requirements summary

```
Node.js     >= 18 (recommended 20+)
PHP         >= 8.2
Composer    2.x
MySQL       8.0+ or MariaDB 10.6+
```
