# Hosting Next.js + Laravel on One cPanel

This guide covers hosting both the **Next.js frontend** and **Laravel backend** on a single cPanel account using two (sub)domains.

**Recommended setup:**
- **Frontend (Next.js):** `yourdomain.com` or `app.yourdomain.com` — Node.js app
- **Backend (Laravel):** `api.yourdomain.com` — PHP, document root = `LARAVEL_BACKEND/public`

---

## 1. Prepare on your computer

### 1.1 Build the Next.js app
```bash
cd ESSEM_BOT
npm install
npm run build
```
Upload the **entire project folder** (or at least the Next.js app + `LARAVEL_BACKEND`) to cPanel. You can use File Manager, FTP, or Git (if cPanel has Git).

### 1.2 Laravel: production env
In `LARAVEL_BACKEND/.env` set (replace with your real domains):
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

FRONTEND_URL=https://yourdomain.com
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,api.yourdomain.com
```

### 1.3 Next.js: production env
In the **root** `.env` (next to `package.json`):
```env
NEXT_PUBLIC_API_URL=https://api.yourdomain.com
NEXT_PUBLIC_USE_MOCK_API=false
```

---

## 2. cPanel: folder layout

Suggested layout under your home directory:

```
~/ (home)
├── laravel/          ← full LARAVEL_BACKEND folder (rename or keep name)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── public/       ← Apache will use THIS as document root for api.yourdomain.com
│   ├── .env
│   └── ...
├── nextjs/           ← full Next.js app (ESSEM_BOT root: package.json, app/, lib/, etc.)
│   ├── app/
│   ├── lib/
│   ├── .next/        ← upload after local "npm run build", or build on server
│   ├── package.json
│   ├── .env
│   └── ...
└── public_html/      ← leave empty or use for redirect; main domain can point to Node app
```

Use whatever folder names your host prefers (e.g. `api` for Laravel, `app` for Next.js). Important: **Laravel’s web root must be `laravel/public`**, not the whole `laravel` folder.

---

## 3. Laravel backend (API) on cPanel

### 3.1 Create subdomain for the API
1. In cPanel go to **Domains** → **Subdomains** (or **Addon Domains** if you use a different domain).
2. Create subdomain: **api** (or e.g. **backend**).
   - Subdomain: `api`
   - Domain: `yourdomain.com`
   - Document root: **`laravel/public`** (or `public` inside the folder where you put Laravel).
   - Example full path: `~/laravel/public` or `/home/username/laravel/public`.
3. Save. Ensure the subdomain’s **document root points only to `public`** (not to the parent `laravel` folder).

### 3.2 Upload Laravel and set env
1. Upload the full **LARAVEL_BACKEND** folder (e.g. as `laravel`) into your home directory.
2. In `laravel/` (not in `public/`), create or edit **`.env`** with production values (see 1.2). Never put `.env` in `public/`.

### 3.3 Composer and dependencies
If the server has SSH:
```bash
cd ~/laravel
php composer.phar install --no-dev --optimize-autoloader
```
Or use cPanel’s **Terminal** and run `composer install --no-dev --optimize-autoloader` if Composer is installed. Do **not** upload the `vendor/` folder from your dev machine if you run Composer on the server.

### 3.4 Laravel setup
```bash
cd ~/laravel
php artisan key:generate   # if APP_KEY is empty
php artisan config:cache
php artisan route:cache
php artisan migrate --force
chmod -R 775 storage bootstrap/cache
```

### 3.5 Permissions
Ensure the web server can write to:
- `storage/`
- `bootstrap/cache/`

In File Manager, set permissions (e.g. 755 for dirs, 644 for files; or 775 for storage/cache if your host requires it).

---

## 4. Next.js frontend on cPanel

Next.js needs **Node.js**. Use cPanel’s **“Setup Node.js App”** (or “Application Manager” / “Node.js Selector”) if available.

### 4.1 Create Node.js application
1. In cPanel open **Setup Node.js App** (or similar).
2. **Create application:**
   - **Node.js version:** 18 or 20 (LTS).
   - **Application root:** folder that contains your Next.js app (e.g. `nextjs` or `ESSEM_BOT`).
   - **Application URL:** your main domain or subdomain (e.g. `yourdomain.com` or `app.yourdomain.com`).
   - **Application startup file:** leave default or set to run `npm start` (see below).

### 4.2 Install and build (on server)
In cPanel Terminal (or SSH), from the **application root**:
```bash
cd ~/nextjs   # or your app folder
npm install --production
npm run build
```

If you already built locally, you can upload the project **including** the `.next` folder and `node_modules` (slower upload), then on the server you may only need to run `npm install --production` and skip `npm run build` (not recommended long-term; building on server is better).

### 4.3 Start script
The Node.js app must run `npm start` (which runs `next start`). In cPanel’s Node.js app settings:
- Set **Run script** / **Start script** to: `npm start` or `node node_modules/next/dist/bin/next start`.
- Set **Application root** to the directory that contains `package.json` and `.next`.

Then use **“Run NPM Install”** and **“Start App”** (or “Restart”) in the same interface.

### 4.4 Point domain to the Node app
- If you used **Application URL** = main domain, cPanel often sets up a proxy from the domain to the Node app port.
- If not, you may need to add a **Proxy (reverse proxy)** in **Advanced** or **Apache Configuration** so that `https://yourdomain.com` proxies to `http://127.0.0.1:3000` (or the port shown in Node.js App).

### 4.5 Env for Next.js on server
In the **same** application root (e.g. `~/nextjs`), create or edit **`.env`**:
```env
NEXT_PUBLIC_API_URL=https://api.yourdomain.com
NEXT_PUBLIC_USE_MOCK_API=false
```
Rebuild after changing `.env` (`npm run build`) and restart the Node app.

---

## 5. Checklist

| Item | Where | Value (example) |
|------|--------|------------------|
| Laravel document root | Subdomain `api.yourdomain.com` | `~/laravel/public` |
| Laravel `.env` | `~/laravel/.env` | `APP_URL=https://api.yourdomain.com`, `FRONTEND_URL=https://yourdomain.com`, `SANCTUM_STATEFUL_DOMAINS=yourdomain.com,api.yourdomain.com` |
| Next.js `.env` | Next.js app root | `NEXT_PUBLIC_API_URL=https://api.yourdomain.com`, `NEXT_PUBLIC_USE_MOCK_API=false` |
| Next.js run command | Node.js App | `npm start` (from app root) |

---

## 6. If your cPanel has no Node.js support

Some shared hosts don’t support running Node.js. Options:

1. **Ask your host** if they support Node.js or can enable it for your account.
2. **Use a different host** for the frontend (e.g. Vercel) and only host Laravel on cPanel: set `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` to the Vercel URL/domain.
3. **Static export (limited):** If you can give up server-side rendering and dynamic routes, you could try `output: 'export'` in `next.config.mjs`, run `npm run build`, and upload the `out/` folder to `public_html`. This is a big change and may break parts of the app; only consider if you understand the limits.

---

## 7. SSL (HTTPS)

- Use cPanel **SSL/TLS** (e.g. Let’s Encrypt) for both `yourdomain.com` and `api.yourdomain.com`.
- After enabling HTTPS, keep using `https://` in:
  - `APP_URL`
  - `FRONTEND_URL`
  - `NEXT_PUBLIC_API_URL`
  - `SANCTUM_STATEFUL_DOMAINS` (domain names only, no scheme).

---

## 8. Troubleshooting

- **CORS errors:** Confirm `FRONTEND_URL` in Laravel `.env` is exactly the origin of the frontend (e.g. `https://yourdomain.com`), no trailing slash. Clear config cache: `php artisan config:clear`.
- **Laravel 500 / blank:** Check `storage/logs/laravel.log`; fix permissions on `storage/` and `bootstrap/cache/`; ensure document root is `public/`.
- **Next.js not loading:** Ensure the Node.js app is **Started** and the domain proxies to the correct port. Check cPanel’s Node.js app logs.
- **API calls fail from frontend:** Verify `NEXT_PUBLIC_USE_MOCK_API=false` and `NEXT_PUBLIC_API_URL=https://api.yourdomain.com`; rebuild Next.js and restart the Node app after changing `.env`.
