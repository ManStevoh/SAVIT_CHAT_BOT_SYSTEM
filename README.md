# SAVIT Chat Bot — Unified Application

The application lives in **`LARAVEL_BACKEND/`** — a single **Laravel 12 + Inertia.js + React** app. The old standalone Next.js frontend has been removed.

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

## Dev logins

| Role | Email | Password |
|------|-------|----------|
| Super Admin | superadmin@savit.local | password |
| Company | demo1@company.local | password |

## Testing

```bash
cd LARAVEL_BACKEND
php artisan test              # PHPUnit (71 tests)
npm run typecheck             # TypeScript
npm run build                 # Vite production build
php -S 127.0.0.1:8080 -t public   # start server (separate terminal)
npm run test:e2e              # Playwright (14 tests)
npm run test:e2e:journey      # Full admin/company/public journeys
```

## Docs

- [Inertia migration guide](LARAVEL_BACKEND/INERTIA_MIGRATION.md)
- [Technical docs](docs/technical/index.md)
- [User guide](docs/user-guide/getting-started.md)
