---
title: Deployment
parent: Technical Documentation
nav_order: 11
---

# Deployment Guide

Production deployment uses **Vercel (frontend)** + **cPanel VPS (backend)**.

## Production URLs

| Component | URL |
|-----------|-----|
| Frontend | https://savit-chat-bot-system.vercel.app |
| Backend | https://savitchat.savitglobalsolutions.com |

## Frontend: Vercel

### Setup

1. Connect GitHub repo to Vercel
2. Set root directory: `FRONTED`
3. Framework preset: Next.js
4. Build command: `npm run build`
5. Output: Next.js default

### Environment variables

| Name | Production value |
|------|------------------|
| `NEXT_PUBLIC_API_URL` | `https://savitchat.savitglobalsolutions.com` |
| `NEXT_PUBLIC_USE_MOCK_API` | `false` |

Redeploy after changing env vars.

## Backend: cPanel / VPS

### Server requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 8.2+ with extensions: mbstring, openssl, pdo, tokenizer, xml, ctype, json, bcmath |
| Composer | 2.x |
| Database | MySQL 8.0+ or MariaDB 10.6+ |
| SSL | Required for webhooks |
| Cron | Required for scheduler |
| Process manager | Supervisor/systemd for queue worker |

### Document root

**Critical:** Point domain document root to Laravel `public/` directory:

```
✅ /home/user/LARAVEL_BACKEND/public
❌ /home/user/LARAVEL_BACKEND
```

Wrong root causes "Index of /" listing and broken API.

### Deployment steps

```bash
cd LARAVEL_BACKEND

# Install dependencies
composer install --no-dev --optimize-autoloader

# Environment
cp .env.example .env
# Edit .env with production values (see Environment Variables doc)
php artisan key:generate

# Database
php artisan migrate --force
php artisan db:seed --force   # First deploy only

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Storage link
php artisan storage:link

# Permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Production `.env` essentials

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://savitchat.savitglobalsolutions.com

FRONTEND_URL=https://savit-chat-bot-system.vercel.app
SANCTUM_STATEFUL_DOMAINS=savit-chat-bot-system.vercel.app,savitchat.savitglobalsolutions.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_password

QUEUE_CONNECTION=database
```

### Queue worker (Supervisor)

```ini
[program:savit-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/LARAVEL_BACKEND/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/LARAVEL_BACKEND/storage/logs/worker.log
```

### Cron scheduler

```cron
* * * * * cd /path/to/LARAVEL_BACKEND && php artisan schedule:run >> /dev/null 2>&1
```

Required for: Growth post publishing, subscription reminders, Meta sync, CRM follow-ups.

### Apache (.htaccess)

Laravel `public/.htaccess` handles URL rewriting. Ensure `mod_rewrite` enabled.

### Nginx (alternative)

```nginx
server {
    listen 443 ssl;
    server_name savitchat.savitglobalsolutions.com;
    root /path/to/LARAVEL_BACKEND/public;

    index index.php;
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Alternative: single cPanel hosting

Both frontend and backend on one account — see [CPANEL-HOSTING.md (legacy)](../FRONTED/docs/CPANEL-HOSTING.md) in repo.

## Meta webhook configuration

After backend is live with HTTPS:

1. Meta Developer → WhatsApp → Configuration
2. Callback URL: `https://savitchat.savitglobalsolutions.com/api/whatsapp/webhook`
3. Verify token: match Admin → Settings
4. Subscribe to **messages**

## Stripe webhook configuration

1. Stripe Dashboard → Webhooks
2. Endpoint: `https://savitchat.savitglobalsolutions.com/api/stripe/webhook`
3. Events: `checkout.session.completed`, subscription events
4. Copy webhook secret to Admin → Payment Gateways

## Post-deploy verification

```bash
curl https://savitchat.savitglobalsolutions.com/up
curl https://savitchat.savitglobalsolutions.com/api/plans
```

| Check | Expected |
|-------|----------|
| `/up` | 200 OK |
| `/api/plans` | JSON plan list |
| Queue worker | Running (`supervisorctl status`) |
| Cron | `schedule:run` in crontab |
| Frontend login | Connects to API |
| WhatsApp test message | Bot replies within seconds |

## Documentation site (GitHub Pages)

See [GitHub Pages Setup](../GITHUB_PAGES_SETUP.md).

## Rollback

```bash
git checkout previous-tag
composer install --no-dev
php artisan migrate
php artisan config:cache
supervisorctl restart savit-queue
```

Always backup database before migrations in production.
