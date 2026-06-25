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
| Super Admin | superadmin@essem.local | password |
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
| `php artisan test` | Backend tests (71 passing) |
| `npm run test:e2e` | Playwright E2E (14 tests) |
| `npm run test:e2e:journey` | Full admin/company/public journey tests |

## E2E testing

Full user journeys are covered by Playwright in `e2e/`:

```bash
php -S 127.0.0.1:8080 -t public
npm run test:e2e           # all 14 tests (~1–5 min)
npm run test:e2e:journey   # journey specs only
```

Journey specs visit every admin page (13), every company dashboard page (10), and the public auth flow end-to-end.

## Migration from Next.js

The old Next.js app at repo root is **deprecated**. Use `LARAVEL_BACKEND/` as the single application going forward.

## Production deploy (cPanel)

Single-domain deployment on cPanel — UI and API together. No Vercel or separate frontend host.

### 1. Upload code

- Deploy `LARAVEL_BACKEND/` to the account (Git pull, SFTP, or cPanel Git Version Control).
- Do **not** expose the project root as the web root.

### 2. Document root

In cPanel → **Domains** → document root, set:

```
/home/<user>/LARAVEL_BACKEND/public
```

Apache must serve from `public/` so `index.php`, `.htaccess`, and `public/build/` assets resolve correctly.

### 3. PHP & extensions

- Select PHP **8.2+** for the domain.
- Enable: mbstring, openssl, pdo_mysql, tokenizer, xml, ctype, json, bcmath, fileinfo.

### 4. Database

- Create MySQL database and user in cPanel.
- Copy credentials into `.env` (`DB_*` keys).

### 5. Environment (`.env`)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://essemchat.essemglobalsolutions.com
FRONTEND_URL=https://essemchat.essemglobalsolutions.com
SANCTUM_STATEFUL_DOMAINS=essemchat.essemglobalsolutions.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=database
VITE_APP_NAME="Essem Chat"
```

Run `php artisan key:generate` once if `APP_KEY` is empty.

### 6. Install dependencies & build assets

Via SSH or cPanel Terminal:

```bash
cd ~/LARAVEL_BACKEND
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

If Node.js is unavailable on the host, run `npm run build` locally or in CI and upload the `public/build/` directory.

### 7. Laravel setup

```bash
php artisan migrate --force
php artisan db:seed --force          # first deploy only
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 775 storage bootstrap/cache
```

### 8. Cron (scheduler)

cPanel → **Cron Jobs** → every minute:

```cron
* * * * * cd /home/<user>/LARAVEL_BACKEND && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Adjust PHP binary path if needed (`which php` on the server).

### 9. Queue worker

cPanel shared hosting often has no long-running processes. Options:

- **Supervisor** (VPS/dedicated): run `php artisan queue:work` — see `docs/technical/deployment.md`.
- **Cron fallback**: add every minute: `php artisan queue:work --stop-when-empty` (processes one batch per run; acceptable for low volume).

WhatsApp replies and Growth jobs require the queue to run.

### 10. SSL

Enable AutoSSL or install a certificate for the domain. Meta and Stripe webhooks require HTTPS.

### 11. Post-deploy checks

| Check | URL / command |
|-------|----------------|
| Health | `curl https://your-domain/up` → 200 |
| Landing | Browser → `/` loads React UI |
| API | `curl https://your-domain/api/plans` → JSON |
| Assets | `public/build/manifest.json` exists |
| Login | `/login` → dashboard after auth |
| Webhooks | Meta callback `https://your-domain/api/whatsapp/webhook` |

### 12. Updates (redeploy)

```bash
cd ~/LARAVEL_BACKEND
git pull                            # or upload changed files
composer install --no-dev
npm ci && npm run build             # skip if no JS changes
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
# restart queue worker if using Supervisor
```
