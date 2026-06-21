# SAVIT Chat Bot — Unified Application

The application lives in **`LARAVEL_BACKEND/`** — a single **Laravel 12 + Inertia.js + React** app.

The Next.js frontend at the repo root is **deprecated**.

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

## Docs

- [Inertia migration guide](LARAVEL_BACKEND/INERTIA_MIGRATION.md)
- [Technical docs](docs/technical/index.md)
- [User guide](docs/user-guide/getting-started.md)

## Branch

Active migration branch: **`feature/inertia-unified`**
