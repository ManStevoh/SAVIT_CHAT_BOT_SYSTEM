# Essem Chat Bot — Unified Application

The platform has two client surfaces:

- **`LARAVEL_BACKEND/`** — Laravel 12 + Inertia.js + React (web UI + API)
- **`MOBILE_APP/`** — Flutter company companion (V1)

## Quick start (web)

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

## Quick start (mobile)

```bash
cd MOBILE_APP
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080/api
```

See [MOBILE_APP/README.md](MOBILE_APP/README.md) and [Mobile App V1 Vision](docs/technical/MOBILE_APP_V1_VISION.md).

## Dev logins

| Role | Email | Password |
|------|-------|----------|
| Super Admin | superadmin@essem.local | password |
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
- [Mobile App V1 Vision](docs/technical/MOBILE_APP_V1_VISION.md)
- [User guide](docs/user-guide/getting-started.md)
