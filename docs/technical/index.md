---
title: Technical Documentation
nav_order: 3
has_children: true
permalink: /technical/
---

# Technical Documentation

Developer and DevOps reference for the Essem Chat Bot platform.

## Repository structure

```
Essem Chat Bot/
├── LARAVEL_BACKEND/   # Unified Laravel 12 + Inertia + React app (UI + API)
├── MOBILE_APP/        # Flutter company companion (V1)
├── docs/              # This documentation site (GitHub Pages)
└── (deprecated)       # Legacy Next.js at repo root — do not deploy
```

> **Note:** Web UI + API live in `LARAVEL_BACKEND/` (Laravel + Inertia). Mobile companion lives in `MOBILE_APP/` (Flutter).

## Documentation map

| Topic | Document |
|-------|----------|
| System design | [Architecture](architecture.md) |
| Languages & frameworks | [Tech Stack](tech-stack.md) |
| Flutter company companion (V1) | [Mobile App V1 Vision](MOBILE_APP_V1_VISION.md) · [Pending map](MOBILE_APP_V1_PENDING.md) |
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
| **AI / ABI (master)** | [Artificial Business Intelligence](AI_ABI_PLATFORM.md) |
| **Digital Nervous System (vision + code map)** | [SAVIT — Digital Nervous System](SAVIT_DIGITAL_NERVOUS_SYSTEM.md) — **north star** |
| **AI Model Orchestration** | [Reasoning, fast chat, vision, STT routing](AI_MODEL_ORCHESTRATION.md) |
| **Honest gaps** | [Shipped vs roadmap](HONEST_REMAINING_GAPS.md) |
| **AI Phase 4** | [Vision, Owner Analytics, Brain, External Tools](AI_PHASE4_OS.md) |
| **AI Phase 5** | [Executive UI, Voice Commands, Approvals, A/B Experiments](AI_PHASE5_OS.md) |
| **ABI Levels 1–20 summary** | [Honest status matrix](ABI_LEVELS_SUMMARY.md) |
| **Enterprise Platform** | [Subscription, Billing, Portals, Builders — Gap Registry](SAVIT_ENTERPRISE_PLATFORM.md) |
| AI Layer 1 — Agent | [AI Agent Commerce OS](AI_AGENT_OS.md) |
| AI Layer 2 — Company | [AI Company OS](AI_COMPANY_OS.md) |
| AI Layer 3 — Platform | [AI Platform OS](AI_PLATFORM_OS.md) |
| AI Layer 4 — Cognitive | [AI Cognitive Architecture](AI_COGNITIVE_OS.md) |
| Common issues | [Troubleshooting](troubleshooting.md) |

## Production URLs

| Service | URL |
|---------|-----|
| Application (UI + API) | https://essemchat.essemglobalsolutions.com |
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
| Super admin | superadmin@essem.local | password | admin |
| Demo company | demo1@company.local | password | company_owner |

Change immediately in production.
