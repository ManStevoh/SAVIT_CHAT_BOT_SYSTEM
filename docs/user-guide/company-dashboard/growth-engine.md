---
title: Growth Engine
parent: Company Dashboard
nav_order: 20
---

# Growth Engine

**URL:** `/dashboard/growth`

The Growth Engine extends WhatsApp commerce with **social media posting**, **attribution tracking**, and **closed-loop revenue analytics**.

## Core loop

```
Social Post → Attribution Link (/g/slug) → Customer clicks → WhatsApp chat → Order → Revenue
```

Every step is tracked so you know which content drives sales.

## Dashboard sections

### Executive summary

- Total reach, clicks, WhatsApp starts, leads, orders, revenue
- Funnel conversion rates
- Ad spend, cost per lead, ROI (if ad spend entered)
- Period comparison

### Posts

| Action | Description |
|--------|-------------|
| **Create post** | Manual draft with caption and platform |
| **AI generate** | OpenAI creates draft posts from brand profile |
| **Smart generate** | Mimics your top-performing past posts |
| **Approve** | Human-in-the-loop before publishing |
| **Schedule** | Set future publish datetime |
| **Publish now** | Immediate publish to connected platform |
| **Share package** | Copy caption + attribution link for manual posting |

Each post gets a unique **attribution link** (`https://your-backend.com/g/{slug}`).

### Social accounts

Connect platforms via OAuth:

- Facebook
- Instagram
- LinkedIn
- TikTok
- X (Twitter)

**Dashboard → Growth → Platforms → Connect**

Redirect URI must be registered: `{APP_URL}/oauth/growth/callback`

For Meta (Facebook/Instagram), also complete page and ad account selection in onboarding wizard.

### Intelligence

| Feature | Description |
|---------|-------------|
| **Patterns** | AI-extracted winning content patterns |
| **Content mix** | Weekly recommended post type balance |
| **Weekly brief** | AI summary of performance |
| **Score drafts** | Pre-publish revenue prediction |
| **Generate variants** | A/B caption variants |

### Competitors

Add competitor social profiles to track and compare positioning.

### Insights

AI-generated actionable recommendations. Mark as read when addressed.

### Agents

Run automated agent pipelines:

- Content generation
- CRM follow-ups for stale leads
- Meta metrics sync

View run history and status.

### Ad spend

Manually enter or CSV-import ad spend for ROI calculations.

### Integrations

Optional connections: GA4, email sync, CRM webhooks.

## Plan limits

| Plan | AI posts/month | Platforms |
|------|----------------|-----------|
| Starter | 20 | 1 |
| Growth | 100 | 3 |
| Enterprise | 500 | 10 |

## Attribution in Chats & Orders

Chats and orders linked to a post show attribution badges in Chats and Orders dashboards.

## CRM follow-ups

Growth CRM agent sends follow-up WhatsApp messages to leads who clicked but didn't order (respects quiet hours and max follow-up limits).

## Getting started checklist

1. Connect at least one social platform
2. Complete Growth onboarding (Meta page selection if applicable)
3. Generate or create first post
4. Approve and publish (or schedule)
5. Share attribution link in post or bio
6. Monitor funnel in Growth analytics

See also [Technical Growth Engine docs](../../technical/growth-engine.md).
