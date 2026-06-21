---
title: Growth Portfolio
parent: Super Admin
nav_order: 46
---

# Super Admin: Growth Portfolio

**URL:** `/admin/growth`

Cross-tenant view of Growth Engine performance and AI-generated portfolio recommendations.

## Portfolio dashboard

Aggregated metrics across all companies using Growth Engine:

| Metric | Description |
|--------|-------------|
| Total posts published | Platform-wide |
| Total attribution clicks | All `/g/{slug}` clicks |
| WhatsApp starts | Chats from attribution |
| Orders & revenue | Attributed commerce |
| Top performing companies | Ranked by attributed revenue |
| Top performing posts | Cross-brand winners |

## Portfolio recommendations

AI generates cross-brand insights:

- Emerging content patterns across tenants
- Industry benchmarks
- Recommended features or plan upgrades

### Actions

| Action | Description |
|--------|-------------|
| **Generate** | Run recommendation engine on demand |
| **Queue** | Dispatch background job for large datasets |
| **Approve** | Mark recommendation for internal action |
| **Mark read** | Dismiss from active queue |

## Scheduled jobs

| Job | Schedule |
|-----|----------|
| GeneratePortfolioRecommendationsJob | Weekly Monday 07:00 |
| PrunePortfolioRecommendationsJob | Weekly Sunday 03:00 |

Requires cron + queue worker.

## Use cases

| Scenario | Action |
|----------|--------|
| Identify best practices | Review top posts across companies (anonymized) |
| Sales intelligence | Show Growth ROI to prospects |
| Product development | Spot feature gaps from aggregate patterns |
| Plan enforcement | Monitor companies approaching Growth limits |

## Company-level detail

Click through to individual company from Companies admin to see their Growth metrics in company context.

Technical reference: [Growth Engine (technical)](../../technical/growth-engine.md)
