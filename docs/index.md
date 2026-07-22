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

Welcome to the official documentation for **Essem Chat Bot (SAVIT)** — the **digital nervous system of a business**: WhatsApp commerce, continuous AI cognition, growth attribution, and owner intelligence in one platform.

## What is SAVIT?

**Beyond WhatsApp + AI + e-commerce:** SAVIT treats every business signal — messages, orders, campaigns, stock, payments — as input to a continuously thinking AI. Customers get agent commerce on WhatsApp; owners get briefings, analytics, and prepared insights before they ask.

Companies subscribe, connect WhatsApp, and the system handles:

- **Instant AI replies** to customer messages (FAQs, catalog, greetings)
- **Order collection** through conversational flows on WhatsApp
- **Payment collection** via M-Pesa STK push, Stripe card payments, or manual instructions
- **Business dashboard** for chats, orders, products, customers, analytics, and team management
- **Growth Engine** for AI social posts, attribution tracking, and closed-loop revenue analytics
- **AI Business OS** — 20 agent tools, unified brain, owner analytics, morning briefs, executive approvals
- **Super Admin panel** for platform operators to manage companies, plans, billing, and integrations

Strategic vision: [Digital Nervous System](technical/SAVIT_DIGITAL_NERVOUS_SYSTEM.md) · Engineering: [AI ABI Platform](technical/AI_ABI_PLATFORM.md) · Platform modules: [Enterprise Blueprint](technical/SAVIT_ENTERPRISE_PLATFORM.md)

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
                                              ↑
                         Essem Mobile (Flutter) — company companion

Growth Engine: Social Post → Attribution Link → WhatsApp → Order → Revenue
```

Mobile vision: [Mobile App V1 Vision](technical/MOBILE_APP_V1_VISION.md)

## Legacy reference documents

Older in-repo specs remain available for deep reference:

- [Growth Engine Spec (legacy)](GROWTH_ENGINE_SPEC.md)
- [WhatsApp Complete Setup Guide](WHATSAPP_COMPLETE_SETUP_GUIDE.md) — **start here** (Meta registration, admin setup, company connect, pricing)
- [WhatsApp Meta Billing Model](WHATSAPP_META_BILLING_MODEL.md) — Tech Provider vs Solution Partner toggle, credit line sharing
- [**Artificial Business Intelligence (ABI) Platform**](technical/AI_ABI_PLATFORM.md) — **Start here** — master verified end-to-end reference (Layers 1–4 + ABI Levels 1–20)
- [AI Agent Commerce OS](technical/AI_AGENT_OS.md) — Layer 1: tools, Chief loop, memory, WhatsApp, proactive
- [AI Company Operating System](technical/AI_COMPANY_OS.md) — Layer 2: reasoning, twin, intent, briefs, reflection
- [AI Platform Operating System](technical/AI_PLATFORM_OS.md) — Layer 3: world model, trust, opportunities, Executive AI
- [AI Cognitive Architecture](technical/AI_COGNITIVE_OS.md) — Layer 4: perception, debate, confidence, DNA, simulation
- [WhatsApp Setup (legacy)](WHATSAPP_SETUP.md)
- [Order Payments (legacy)](ORDER_PAYMENTS.md)
- [Sample Data Import (legacy)](SAMPLE_DATA_IMPORT.md)

## Publishing this documentation

See [GitHub Pages Setup](GITHUB_PAGES_SETUP.md) for instructions to publish this site and get a shareable link.
