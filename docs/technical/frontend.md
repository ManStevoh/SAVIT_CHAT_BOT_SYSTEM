---
title: Frontend
parent: Technical Documentation
nav_order: 3
---

# Frontend Application

**Path:** `LARAVEL_BACKEND/resources/js/`  
**Stack:** Inertia.js + React 19 + Vite + Tailwind CSS

The UI is a React SPA embedded in Laravel. Laravel `web.php` routes render Inertia pages; data mutations and reads use the existing JSON API at `/api/*` on the same origin.

## Route map

Routes are defined in `LARAVEL_BACKEND/routes/web.php` and mapped to React pages under `resources/js/Pages/`.

### Public routes

| Route | Page component | Description |
|-------|----------------|-------------|
| `/` | `Pages/Home/page.tsx` | Landing page |
| `/login` | `Pages/Auth/login/page.tsx` | Login |
| `/register` | `Pages/Auth/register/page.tsx` | Registration |
| `/forgot-password` | `Pages/Auth/forgot-password/page.tsx` | Password reset request |
| `/reset-password` | `Pages/Auth/reset-password/page.tsx` | Password reset form |
| `/order-paid` | `Pages/order-paid/page.tsx` | Post-Stripe order thank-you |

### Company dashboard

| Route | Page component |
|-------|----------------|
| `/dashboard` | `Pages/dashboard/page.tsx` |
| `/dashboard/chats` | `Pages/dashboard/chats/page.tsx` |
| `/dashboard/orders` | `Pages/dashboard/orders/page.tsx` |
| `/dashboard/products` | `Pages/dashboard/products/page.tsx` |
| `/dashboard/customers` | `Pages/dashboard/customers/page.tsx` |
| `/dashboard/faq` | `Pages/dashboard/faq/page.tsx` |
| `/dashboard/analytics` | `Pages/dashboard/analytics/page.tsx` |
| `/dashboard/growth` | `Pages/dashboard/growth/page.tsx` |
| `/dashboard/subscription` | `Pages/dashboard/subscription/page.tsx` |
| `/dashboard/settings` | `Pages/dashboard/settings/page.tsx` |

### Super admin

| Route | Page component |
|-------|----------------|
| `/admin` | `Pages/admin/page.tsx` |
| `/admin/companies` | `Pages/admin/companies/page.tsx` |
| `/admin/users` | `Pages/admin/users/page.tsx` |
| `/admin/plans` | `Pages/admin/plans/page.tsx` |
| `/admin/subscriptions` | `Pages/admin/subscriptions/page.tsx` |
| `/admin/revenue` | `Pages/admin/revenue/page.tsx` |
| `/admin/payment-gateways` | `Pages/admin/payment-gateways/page.tsx` |
| `/admin/growth` | `Pages/admin/growth/page.tsx` |
| `/admin/testimonials` | `Pages/admin/testimonials/page.tsx` |
| `/admin/landing-faqs` | `Pages/admin/landing-faqs/page.tsx` |
| `/admin/settings` | `Pages/admin/settings/page.tsx` |
| `/admin/logs` | `Pages/admin/logs/page.tsx` |
| `/admin/ai-usage` | `Pages/admin/ai-usage/page.tsx` |

## Directory layout

```
resources/js/
├── app.tsx              # Inertia bootstrap, layout resolution
├── Pages/               # One page per screen (29 total)
├── layouts/             # AuthLayout, DashboardLayout, AdminLayout
├── components/
│   ├── ui/              # shadcn/Radix primitives
│   ├── dashboard/       # Dashboard-specific components
│   ├── admin/           # Admin panel components
│   └── growth/          # Growth Engine UI
├── lib/
│   ├── api-client.ts    # HTTP client, relative /api/* paths
│   ├── api-hooks.ts     # SWR hooks for API resources
│   ├── api-actions.ts   # Mutations (POST/PUT/DELETE)
│   ├── auth-cookie.ts   # Client-side route-guard cookies
│   └── mock-data.ts     # Mock data when VITE_USE_MOCK_API=true
└── shims/               # next/link & next/navigation → Inertia adapters
```

## Key libraries

| File | Purpose |
|------|---------|
| `@inertiajs/react` | Page navigation, form submissions, shared props |
| `lib/api-client.ts` | Base HTTP client, auth header, error handling |
| `lib/api-hooks.ts` | SWR hooks for all API resources |
| `lib/api-actions.ts` | Mutations (POST/PUT/DELETE) |
| `shims/next-link.tsx` | Drop-in `Link` using Inertia `<Link>` |
| `shims/next-navigation.ts` | `useRouter`, `usePathname` via Inertia |
| `components/ui/*` | shadcn/ui primitives |

## Authentication flow

```typescript
// Login
POST /api/auth/login → { token, user }
localStorage.setItem('auth_token', token)
setAuthCookie(role, rememberMe)

// Subsequent requests
headers: { Authorization: `Bearer ${token}` }

// Logout
POST /api/auth/logout
localStorage.removeItem('auth_token')
clearAuthCookie()
```

Layouts check token presence and redirect to `/login`. Auth cookies (`essem_token`, `essem_role`) support client-side route protection.

## Data fetching pattern

Initial page shell loads via Inertia. Interactive data uses SWR against same-origin `/api/*`:

```typescript
export function useChats() {
  return useSWR('/api/company/chats', fetcher)
}
```

SWR provides caching, revalidation, and loading states. Polling interval configured for chat inbox.

## Environment variables

Set in `.env` (Vite exposes `VITE_*` at build time):

| Variable | Required | Description |
|----------|----------|-------------|
| `VITE_APP_NAME` | No | Browser tab title prefix |
| `VITE_API_URL` | No | Override API base (omit for same-origin `/api/*`) |
| `VITE_USE_MOCK_API` | No | `true` = mock data, omit or `false` = live API |

## Development

Run PHP and Vite together:

```bash
cd LARAVEL_BACKEND
composer install && npm install
php artisan migrate --seed

# Terminal 1
php artisan serve --port=8080

# Terminal 2
npm run dev
```

Open **http://127.0.0.1:8080**

## Build & deploy

```bash
npm run build      # Production assets → public/build/
npm run typecheck  # TypeScript validation
```

Assets are served by Laravel from `public/build/`. Re-run `npm run build` after frontend changes before deploying.

## Styling conventions

- Tailwind utility classes via `@tailwindcss/vite`
- CSS variables for theme colors (supports company/admin branding)
- Dark mode: follows system preference where implemented

## Mock API mode

Set `VITE_USE_MOCK_API=true` in `.env` and rebuild (or use `npm run dev`) to develop UI without a live API. Hooks return data from `lib/mock-data.ts`.

## E2E testing (Playwright)

Tests live in `LARAVEL_BACKEND/e2e/`:

| Spec | Coverage |
|------|----------|
| `login.spec.ts` | Login page, auth redirects, company + admin sign-in |
| `dashboard.spec.ts` | Dashboard home, growth page |
| `admin.spec.ts` | Admin overview, growth |
| `app-public.spec.ts` | Landing page, auth guards |
| `journey-public.spec.ts` | Landing → register → forgot password → login → order-paid |
| `journey-company.spec.ts` | All 10 dashboard pages + sidebar nav + logout |
| `journey-admin.spec.ts` | All 13 admin pages + sidebar nav + logout |

```bash
cd LARAVEL_BACKEND
php -S 127.0.0.1:8080 -t public   # start server first
npm run test:e2e                  # 14 tests
npm run test:e2e:journey          # full journey tests only
```

Shared helpers: `e2e/helpers/auth.ts` (login, logout, page assertions).

## Known technical notes

- `next/link` and `next/navigation` imports resolve to Inertia shims (Vite aliases in `vite.config.js`)
- File uploads use POST (not PUT) for multipart — PHP limitation documented in api.php
- Growth OAuth callback redirects to `/dashboard/growth?growth_oauth=success`
- Legacy Next.js app at repo root is deprecated; do not deploy separately
