Essem Chat — cPanel upload package
==================================

DOMAIN: https://ai.essemdigital.com
DOCUMENT ROOT must point to:  .../LARAVEL_BACKEND/public   (NOT the parent folder)

STEP 1 — Upload & extract
-------------------------
1. Upload this zip to cPanel File Manager (e.g. into /home/youruser/)
2. Extract so you have: /home/youruser/LARAVEL_BACKEND/
3. In cPanel → Domains, set ai.essemdigital.com document root to:
   /home/youruser/LARAVEL_BACKEND/public

STEP 2 — Create .env
--------------------
Copy .env.example to .env in LARAVEL_BACKEND/ and edit:

  APP_NAME="Essem Chat"
  APP_ENV=production
  APP_DEBUG=false
  APP_URL=https://ai.essemdigital.com
  FRONTEND_URL=https://ai.essemdigital.com
  SANCTUM_STATEFUL_DOMAINS=ai.essemdigital.com

  DB_CONNECTION=mysql
  DB_HOST=localhost
  DB_DATABASE=your_db_name
  DB_USERNAME=your_db_user
  DB_PASSWORD=your_db_password

  SESSION_DRIVER=database
  SESSION_SECURE_COOKIE=true
  QUEUE_CONNECTION=database

Create the MySQL database in cPanel → MySQL Databases first.

STEP 3 — Server setup
---------------------

### Option A — Terminal or SSH (if available)
  cd ~/LARAVEL_BACKEND
  rm -f public/hot
  php artisan key:generate
  php artisan migrate --force
  php artisan storage:link
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  chmod -R 775 storage bootstrap/cache

### Option B — No SSH / no Terminal (cPanel only)

**File Manager (manual, one-time):**
1. Delete `LARAVEL_BACKEND/public/hot` if it exists (select file → Delete).
2. Right-click folders → **Change Permissions** → set **775** on:
   - `LARAVEL_BACKEND/storage` (and all subfolders — check “Recurse”)
   - `LARAVEL_BACKEND/bootstrap/cache`

**Cron Jobs (one-time setup — run each once, then remove the cron):**

In cPanel → **Cron Jobs**, use “Standard” and paste ONE command per cron.
Replace `/home/youruser/LARAVEL_BACKEND` with your real path (see File Manager).

  /usr/local/bin/php /home/youruser/LARAVEL_BACKEND/artisan key:generate --force

  /usr/local/bin/php /home/youruser/LARAVEL_BACKEND/artisan migrate --force

  /usr/local/bin/php /home/youruser/LARAVEL_BACKEND/artisan storage:link

  /usr/local/bin/php /home/youruser/LARAVEL_BACKEND/artisan config:cache

  /usr/local/bin/php /home/youruser/LARAVEL_BACKEND/artisan route:cache

  /usr/local/bin/php /home/youruser/LARAVEL_BACKEND/artisan view:cache

Tips:
- If `php` fails, try `/usr/bin/php` or run `which php` from host docs.
- Schedule each cron **once** (e.g. next minute), wait 2 minutes, check site, **delete** that cron.
- Do NOT leave `key:generate` on a repeating schedule.
- `migrate --force` on a repeating cron is usually safe but unnecessary after first run.

**APP_KEY without artisan:** If key:generate fails, in `.env` set:
  APP_KEY=base64:YOUR_RANDOM_32_BYTE_BASE64_STRING
(Generate one locally: `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`)

STEP 4 — Cron (every minute, keep forever)
-----------------------------------------
This one SHOULD stay on cron permanently (not the same as Step 3):

  * * * * * /usr/local/bin/php /home/youruser/LARAVEL_BACKEND/artisan schedule:run >> /dev/null 2>&1

(If your host requires full path + cd, use:)
  * * * * * cd /home/youruser/LARAVEL_BACKEND && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1

STEP 5 — Queue worker (WhatsApp campaigns + AI replies)
-------------------------------------------------------
Ideal: Supervisor or a host “long-running process” (if your plan includes it):
  php artisan queue:work --sleep=3 --tries=3

**No SSH?** Some hosts cannot run a permanent queue worker. Workaround — cron every 1–5 minutes:
  */5 * * * * cd /home/youruser/LARAVEL_BACKEND && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=240 >> /dev/null 2>&1
This processes pending jobs in batches (slower than a real worker, but works on basic cPanel).

STEP 6 — Verify
---------------
  https://ai.essemdigital.com/up          → should return OK
  https://ai.essemdigital.com             → landing page loads (no [::1]:5173 errors)

Default super admin (if you run db:seed):
  Email: superadmin@essem.local
  Password: password
  CHANGE PASSWORD IMMEDIATELY in production.

WHAT IS INCLUDED IN THIS ZIP
----------------------------
  vendor/          PHP dependencies (production)
  public/build/    Compiled frontend (Vite) — required for UI
  app/, config/, database/, resources/, routes/, etc.

NOT INCLUDED (by design)
------------------------
  .env             You create this on the server (never upload secrets)
  node_modules/    Not needed in production
  tests/           Dev only
  public/hot       Vite dev marker — must never exist on production
