---
title: Frontend
parent: Technical Documentation
nav_order: 3
---

# Frontend Application

**Path:** `FRONTED/`  
**Framework:** Next.js 16 App Router

## Route map

### Public routes

| Route | File | Description |
|-------|------|-------------|
| `/` | `app/page.tsx` | Landing page |
| `/login` | `app/(auth)/login/page.tsx` | Login |
| `/register` | `app/(auth)/register/page.tsx` | Registration |
| `/forgot-password` | `app/(auth)/forgot-password/page.tsx` | Password reset request |
| `/reset-password` | `app/(auth)/reset-password/page.tsx` | Password reset form |
| `/order-paid` | `app/order-paid/page.tsx` | Post-Stripe order thank-you |

### Company dashboard

| Route | File |
|-------|------|
| `/dashboard` | `app/dashboard/page.tsx` |
| `/dashboard/chats` | `app/dashboard/chats/page.tsx` |
| `/dashboard/orders` | `app/dashboard/orders/page.tsx` |
| `/dashboard/products` | `app/dashboard/products/page.tsx` |
| `/dashboard/customers` | `app/dashboard/customers/page.tsx` |
| `/dashboard/faq` | `app/dashboard/faq/page.tsx` |
| `/dashboard/analytics` | `app/dashboard/analytics/page.tsx` |
| `/dashboard/growth` | `app/dashboard/growth/page.tsx` |
| `/dashboard/subscription` | `app/dashboard/subscription/page.tsx` |
| `/dashboard/settings` | `app/dashboard/settings/page.tsx` |

### Super admin

| Route | File |
|-------|------|
| `/admin` | `app/admin/page.tsx` |
| `/admin/companies` | `app/admin/companies/page.tsx` |
| `/admin/users` | `app/admin/users/page.tsx` |
| `/admin/plans` | `app/admin/plans/page.tsx` |
| `/admin/subscriptions` | `app/admin/subscriptions/page.tsx` |
| `/admin/revenue` | `app/admin/revenue/page.tsx` |
| `/admin/payment-gateways` | `app/admin/payment-gateways/page.tsx` |
| `/admin/growth` | `app/admin/growth/page.tsx` |
| `/admin/testimonials` | `app/admin/testimonials/page.tsx` |
| `/admin/landing-faqs` | `app/admin/landing-faqs/page.tsx` |
| `/admin/settings` | `app/admin/settings/page.tsx` |
| `/admin/logs` | `app/admin/logs/page.tsx` |
| `/admin/ai-usage` | `app/admin/ai-usage/page.tsx` |

## Key libraries

| File | Purpose |
|------|---------|
| `lib/api-client.ts` | Base HTTP client, auth header, error handling |
| `lib/api-hooks.ts` | SWR hooks for all API resources |
| `lib/api-actions.ts` | Mutations (POST/PUT/DELETE) |
| `lib/auth.ts` | Token storage, login/logout helpers |
| `lib/mock-data.ts` | Mock API data when `NEXT_PUBLIC_USE_MOCK_API=true` |
| `components/ui/*` | shadcn/ui primitives |
| `components/dashboard/*` | Dashboard-specific components |
| `components/admin/*` | Admin panel components |
| `components/growth/*` | Growth Engine UI |

## Authentication flow

```typescript
// Login
POST /api/auth/login → { token, user }
localStorage.setItem('auth_token', token)

// Subsequent requests
headers: { Authorization: `Bearer ${token}` }

// Logout
POST /api/auth/logout
localStorage.removeItem('auth_token')
```

Protected layouts check token presence and redirect to `/login`.

## Data fetching pattern

```typescript
// SWR hook example
export function useChats() {
  return useSWR('/api/company/chats', fetcher)
}
```

SWR provides caching, revalidation, and loading states. Polling interval configured for chat inbox.

## Environment variables

| Variable | Required | Description |
|----------|----------|-------------|
| `NEXT_PUBLIC_API_URL` | Yes | Laravel backend base URL |
| `NEXT_PUBLIC_USE_MOCK_API` | No | `true` = mock data, `false` = live API |

## Build & deploy

```bash
npm run build    # Production build
npm run start    # Production server (local)
npm run dev      # Development with HMR
npm run test:e2e # Playwright tests
```

Vercel auto-deploys from connected Git branch. Set env vars in Vercel dashboard.

## Component organization

```
components/
├── landing/       # Hero, pricing, features
├── dashboard/     # Sidebar, chat inbox, order tables
├── admin/         # Admin tables and forms
├── growth/        # Growth Engine tabs
└── ui/            # shadcn primitives (button, dialog, etc.)
```

## Styling conventions

- Tailwind utility classes
- CSS variables for theme colors (supports company/admin branding)
- Dark mode: follows system preference where implemented

## Mock API mode

Set `NEXT_PUBLIC_USE_MOCK_API=true` for frontend development without backend. All hooks return data from `lib/mock-data.ts`.

## Known technical notes

- TypeScript build errors ignored in production build (`ignoreBuildErrors: true`)
- File uploads use POST (not PUT) for multipart — PHP limitation documented in api.php
- Growth OAuth callback redirects to `/dashboard/growth?growth_oauth=success`
