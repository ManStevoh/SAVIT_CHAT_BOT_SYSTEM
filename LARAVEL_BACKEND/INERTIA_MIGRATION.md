# Laravel + Inertia Unified App

The frontend UI now runs inside Laravel via **Inertia.js + React**. All 29 screens are served from Laravel web routes while the existing JSON API (`/api/*`) handles data mutations.

## Quick start

```bash
cd LARAVEL_BACKEND
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php -S 127.0.0.1:8080 -t public
```

Open **http://127.0.0.1:8080**

## Dev credentials

| Role | Email | Password |
|------|-------|----------|
| Super Admin | superadmin@savit.local | password |
| Company 1 | demo1@company.local | password |
| Company 2 | demo2@company.local | password |

## Architecture

```
LARAVEL_BACKEND/
├── app/Http/Controllers/Web/PageController.php   # Inertia page routes
├── resources/js/
│   ├── Pages/          # All 29 screens (migrated from Next.js)
│   ├── components/ui/  # shadcn/Radix UI (unchanged)
│   ├── lib/            # API client, hooks, actions
│   ├── layouts/        # Auth, Dashboard, Admin layouts
│   └── shims/          # next/link & next/navigation → Inertia adapters
├── routes/web.php      # All UI routes
└── routes/api.php      # JSON API (unchanged, 138 endpoints)
```

## What changed

- **UI routing**: Laravel `web.php` + Inertia (was Next.js App Router)
- **UI packages**: Same shadcn/Radix/Tailwind — users see identical design
- **API layer**: Kept as `/api/*` with Bearer token auth (decoupled from UI)
- **Deploy**: Single Laravel app (no separate Vercel frontend needed)

## Scripts

| Command | Purpose |
|---------|---------|
| `npm run dev` | Vite HMR (with `php artisan serve` or `php -S`) |
| `npm run build` | Production assets → `public/build/` |
| `npm run typecheck` | TypeScript validation |
| `php artisan test` | Backend tests (39 passing) |

## Migration from Next.js

The old Next.js app at repo root is **deprecated**. Use `LARAVEL_BACKEND/` as the single application going forward.
