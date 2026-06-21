---
title: Tech Stack
parent: Technical Documentation
nav_order: 2
---

# Tech Stack

## Frontend (`FRONTED/`)

| Category | Technology | Version |
|----------|------------|---------|
| Framework | Next.js (App Router) | 16.1.6 |
| UI library | React | 19 |
| Language | TypeScript | 5.7 |
| Styling | Tailwind CSS | 4 |
| Components | shadcn/ui (Radix UI) | — |
| Icons | lucide-react | — |
| Data fetching | SWR | 2.x |
| Forms | react-hook-form + zod | — |
| Charts | recharts | — |
| Analytics | @vercel/analytics | — |
| E2E tests | Playwright | — |
| Package manager | npm (pnpm-lock also present) | — |

### Frontend config notes

- `next.config.mjs`: `ignoreBuildErrors: true` for TypeScript (technical debt)
- Images: `unoptimized: true`
- No server-side API routes — all data from Laravel

## Backend (`LARAVEL_BACKEND/`)

| Category | Technology | Version |
|----------|------------|---------|
| Framework | Laravel | 12 |
| Language | PHP | 8.2+ |
| Auth | Laravel Sanctum | Bearer tokens |
| Queue | Database driver | default |
| Scheduler | Laravel Schedule | bootstrap/app.php |
| CSV | league/csv | Import/export |
| Asset build | Vite + Tailwind | Minimal Laravel UI |
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
| Frontend hosting | Vercel |
| Backend hosting | cPanel VPS (savitchat.savitglobalsolutions.com) |
| DNS / SSL | Provider-managed |
| Email | SMTP (configurable in admin) |

## Testing stack

| Layer | Tool | Location |
|-------|------|----------|
| Backend unit/feature | PHPUnit | `LARAVEL_BACKEND/tests/` |
| Frontend E2E | Playwright | `FRONTED/e2e/` |
| CI script | `npm run test:ci` | tsc + Playwright |

No GitHub Actions CI configured in repository.

## Version requirements summary

```
Node.js     >= 18 (recommended 20+)
PHP         >= 8.2
Composer    2.x
MySQL       8.0+ or MariaDB 10.6+
```
