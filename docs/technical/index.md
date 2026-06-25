---
title: Technical Documentation
nav_order: 3
has_children: true
permalink: /technical/
---

# Technical Documentation

Developer and DevOps reference for the SAVIT Chat Bot platform.

## Repository structure

```
SAVIT_CHAT_BOT/
├── LARAVEL_BACKEND/   # Unified Laravel 12 + Inertia + React app (UI + API)
├── docs/              # This documentation site (GitHub Pages)
└── (deprecated)       # Legacy Next.js at repo root — do not deploy
```

> **Note:** The application is `LARAVEL_BACKEND/` only (Laravel + Inertia). The old Next.js frontend has been removed.

## Documentation map

| Topic | Document |
|-------|----------|
| System design | [Architecture](architecture.md) |
| Languages & frameworks | [Tech Stack](tech-stack.md) |
| Inertia + React UI | [Frontend](frontend.md) |
| Laravel API | [Backend](backend.md) |
| REST endpoints | [API Reference](api-reference.md) |
| Data model | [Database Schema](database.md) |
| WhatsApp bot pipeline | [WhatsApp Bot](whatsapp-bot.md) |
| Stripe, M-Pesa, Paystack | [Payments](payments.md) |
| Social attribution | [Growth Engine](growth-engine.md) |
| Sanctum, roles, CORS | [Auth & Security](auth-security.md) |
| Single-app cPanel production | [Deployment](deployment.md) |
| Automated CI/CD (local → server) | [CI/CD Pipeline](ci-cd.md) |
| `.env` reference | [Environment Variables](environment-variables.md) |
| Cron, queues, jobs | [Queues & Scheduler](queues-scheduler.md) |
| Common issues | [Troubleshooting](troubleshooting.md) |

## Production URLs

| Service | URL |
|---------|-----|
| Application (UI + API) | https://savitchat.savitglobalsolutions.com |
| Health check | `GET /up` on the same domain |

## Quick local setup

```bash
cd LARAVEL_BACKEND
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build

# Terminal 1 — PHP server
php -S 127.0.0.1:8080 -t public

# Terminal 2 — Vite HMR (optional)
npm run dev

# Terminal 3 — queue worker
php artisan queue:work
```

Open **http://127.0.0.1:8080**

## Default seeded credentials

| Account | Email | Password | Role |
|---------|-------|----------|------|
| Super admin | superadmin@savit.local | password | admin |
| Demo company | demo1@company.local | password | company_owner |

Change immediately in production.
