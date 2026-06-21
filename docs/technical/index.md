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
├── FRONTED/           # Next.js 16 frontend (App Router)
├── LARAVEL_BACKEND/   # Laravel 12 API + webhooks + jobs
└── docs/              # This documentation site (GitHub Pages)
```

> **Note:** The frontend folder is named `FRONTED` (not FRONTEND).

## Documentation map

| Topic | Document |
|-------|----------|
| System design | [Architecture](architecture.md) |
| Languages & frameworks | [Tech Stack](tech-stack.md) |
| Next.js application | [Frontend](frontend.md) |
| Laravel API | [Backend](backend.md) |
| REST endpoints | [API Reference](api-reference.md) |
| Data model | [Database Schema](database.md) |
| WhatsApp bot pipeline | [WhatsApp Bot](whatsapp-bot.md) |
| Stripe, M-Pesa, Paystack | [Payments](payments.md) |
| Social attribution | [Growth Engine](growth-engine.md) |
| Sanctum, roles, CORS | [Auth & Security](auth-security.md) |
| Vercel + cPanel production | [Deployment](deployment.md) |
| `.env` reference | [Environment Variables](environment-variables.md) |
| Cron, queues, jobs | [Queues & Scheduler](queues-scheduler.md) |
| Common issues | [Troubleshooting](troubleshooting.md) |

## Production URLs

| Service | URL |
|---------|-----|
| Frontend | https://savit-chat-bot-system.vercel.app |
| Backend API | https://savitchat.savitglobalsolutions.com |
| Health check | https://savitchat.savitglobalsolutions.com/up |

## Quick local setup

### Backend

```bash
cd LARAVEL_BACKEND
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve          # http://localhost:8000
php artisan queue:work     # separate terminal
```

### Frontend

```bash
cd FRONTED
cp .env.example .env.local
# NEXT_PUBLIC_API_URL=http://localhost:8000
# NEXT_PUBLIC_USE_MOCK_API=false
npm install
npm run dev                # http://localhost:3000
```

### Dev stack (all-in-one)

```bash
cd LARAVEL_BACKEND
composer dev   # Runs serve + queue + logs + Vite concurrently
```

## Default seeded credentials

| Account | Email | Password | Role |
|---------|-------|----------|------|
| Super admin | admin@savit.local | password | admin |
| Sample company | (from CompanySeeder) | password | company_owner |

Change immediately in production.
