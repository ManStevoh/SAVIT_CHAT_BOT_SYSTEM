# Production deployment URLs

- **Frontend (Vercel):** https://savit-chat-bot-system.vercel.app  
- **Backend (Laravel):** https://savitchat.savitglobalsolutions.com  

---

## 1. Vercel (frontend)

In your Vercel project: **Settings → Environment Variables**, add:

| Name | Value | Environments |
|------|--------|--------------|
| `NEXT_PUBLIC_API_URL` | `https://savitchat.savitglobalsolutions.com` | Production (and Preview if you want) |
| `NEXT_PUBLIC_USE_MOCK_API` | `false` | Production (and Preview if you want) |

Redeploy after changing env vars so the new values are baked in.

---

## 2. Backend server (savitchat.savitglobalsolutions.com)

In **LARAVEL_BACKEND/.env** on the server, set:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://savitchat.savitglobalsolutions.com

FRONTEND_URL=https://savit-chat-bot-system.vercel.app
SANCTUM_STATEFUL_DOMAINS=savit-chat-bot-system.vercel.app,savitchat.savitglobalsolutions.com
```

Then run:

```bash
php artisan config:clear
php artisan config:cache
```

---

## 3. Backend document root

Your backend URL currently shows “Index of /”, which usually means the domain is pointing at the wrong folder. Point the **document root** for `savitchat.savitglobalsolutions.com` to the **`public`** directory of Laravel, e.g.:

- **Correct:** `.../LARAVEL_BACKEND/public` or `.../laravel/public`  
- **Wrong:** `.../LARAVEL_BACKEND` (root of the Laravel app)

After fixing, the API should be reachable at e.g. `https://savitchat.savitglobalsolutions.com/api/...` and the “Index of /” listing will disappear.
