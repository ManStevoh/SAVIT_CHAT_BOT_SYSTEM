# SAVIT Chat Bot

Multi-tenant SaaS platform for **WhatsApp AI commerce** and **social media growth attribution**.

- **Frontend:** Next.js 16 (`FRONTED/`) — deployed on Vercel
- **Backend:** Laravel 12 (`LARAVEL_BACKEND/`) — deployed on cPanel VPS
- **Live app:** https://savit-chat-bot-system.vercel.app
- **API:** https://savitchat.savitglobalsolutions.com

## Documentation

Full documentation is in the [`docs/`](docs/) folder, publishable to **GitHub Pages**:

| Section | Description |
|---------|-------------|
| [User Guide](docs/user-guide/) | Company owners, customers, super admins |
| [Technical Docs](docs/technical/) | Architecture, API, deployment, DevOps |
| [GitHub Pages Setup](docs/GITHUB_PAGES_SETUP.md) | How to publish and share docs link |

### Publish docs to GitHub Pages

1. Push repo to GitHub
2. **Settings → Pages → Source:** branch `main`, folder `/docs`
3. Share URL: `https://<org>.github.io/<repo>/`

See [docs/GITHUB_PAGES_SETUP.md](docs/GITHUB_PAGES_SETUP.md) for details.

## Quick start (local)

### Backend

```bash
cd LARAVEL_BACKEND
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
php artisan queue:work   # separate terminal
```

### Frontend

```bash
cd FRONTED
cp .env.example .env.local
npm install
npm run dev
```

Default admin: `admin@savit.local` / `password`

## Features

- WhatsApp Cloud API bot (FAQ, AI, orders, payments)
- Company dashboard (chats, orders, products, analytics)
- Subscriptions (Stripe, M-Pesa, Paystack)
- Growth Engine (social posts, attribution, ROI)
- Super admin panel (tenants, billing, platform config)

## License

Proprietary — SAVIT Global Solutions
