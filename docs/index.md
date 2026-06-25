---
title: Home
layout: default
nav_order: 1
description: "Essem Chat Bot — WhatsApp AI Commerce & Growth Engine"
permalink: /
---

# Essem Chat Bot Documentation

> **Enable docs site:** [Settings → Pages](https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM/settings/pages) → Deploy from branch → `main` → `/docs` → Save.  
> **URL:** https://manstevoh.github.io/SAVIT_CHAT_BOT_SYSTEM/

Welcome to the official documentation for **Essem Chat Bot** — a multi-tenant SaaS platform that lets businesses connect WhatsApp, automate customer conversations with AI, manage orders and products, collect payments, and grow revenue through social media attribution.

## What is Essem Chat Bot?

Essem Chat Bot is an end-to-end commerce platform built for businesses that sell through WhatsApp. Companies subscribe to the platform, connect their WhatsApp Business number, and the system handles:

- **Instant AI replies** to customer messages (FAQs, catalog, greetings)
- **Order collection** through conversational flows on WhatsApp
- **Payment collection** via M-Pesa STK push, Stripe card payments, or manual instructions
- **Business dashboard** for chats, orders, products, customers, analytics, and team management
- **Growth Engine** for AI social posts, attribution tracking, and closed-loop revenue analytics
- **Super Admin panel** for platform operators to manage companies, plans, billing, and integrations

## Production URLs

| Component | URL |
|-----------|-----|
| Web application (UI + API) | [essemchat.essemglobalsolutions.com](https://essemchat.essemglobalsolutions.com) |

## Documentation sections

### [User Guide](user-guide/)

Step-by-step guides for every role and user journey:

- **Company owners** — register, connect WhatsApp, manage products/FAQs, handle chats and orders, subscribe, use Growth Engine
- **End customers** — shopping and ordering via WhatsApp, payment flows
- **Super admins** — platform management, billing, integrations, monitoring

### [Technical Documentation](technical/)

Architecture, API reference, database schema, deployment, environment variables, queues, and troubleshooting for developers and DevOps.

## Quick start

| Role | Start here |
|------|------------|
| New business owner | [Getting Started](user-guide/getting-started.md) |
| Platform administrator | [Super Admin Overview](user-guide/super-admin/overview.md) |
| Developer / DevOps | [Architecture Overview](technical/architecture.md) |
| Deploy to production | [Deployment Guide](technical/deployment.md) |

## System at a glance

```
Customer (WhatsApp)  →  Meta Cloud API  →  Laravel (API + Inertia UI)  →  AI / FAQ / Orders
                                              ↓
Company Dashboard (React)  ←  REST API  ←  Database + Queue Jobs

Growth Engine: Social Post → Attribution Link → WhatsApp → Order → Revenue
```

## Legacy reference documents

Older in-repo specs remain available for deep reference:

- [Growth Engine Spec (legacy)](GROWTH_ENGINE_SPEC.md)
- [WhatsApp Complete Setup Guide](WHATSAPP_COMPLETE_SETUP_GUIDE.md) — **start here** (Meta registration, admin setup, company connect, pricing)
- [WhatsApp Setup (legacy)](WHATSAPP_SETUP.md)
- [Order Payments (legacy)](ORDER_PAYMENTS.md)
- [Sample Data Import (legacy)](SAMPLE_DATA_IMPORT.md)

## Publishing this documentation

See [GitHub Pages Setup](GITHUB_PAGES_SETUP.md) for instructions to publish this site and get a shareable link.
