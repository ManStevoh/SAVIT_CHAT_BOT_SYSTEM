# Production deployment URLs

- **App (UI + API):** https://relayiq.app

---

## 1. Backend server (relayiq.app)

In **LARAVEL_BACKEND/.env** on the server, set:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://relayiq.app

FRONTEND_URL=https://relayiq.app
SANCTUM_STATEFUL_DOMAINS=relayiq.app
```

Then run:

```bash
php artisan config:clear
php artisan config:cache
```

---

## 2. Backend document root

Point the **document root** for `relayiq.app` to the **`public`** directory of Laravel, e.g.:

- **Correct:** `.../LARAVEL_BACKEND/public`
- **Wrong:** `.../LARAVEL_BACKEND` (root of the Laravel app)

After fixing, the API should be reachable at e.g. `https://relayiq.app/api/...`.
