---
title: Content Management
parent: Super Admin
nav_order: 47
---

# Super Admin: Content Management

Manage public-facing marketing content on the landing page.

## Testimonials

**URL:** `/admin/testimonials`

Social proof displayed on landing page.

| Field | Description |
|-------|-------------|
| Name | Customer or business name |
| Role / company | Subtitle |
| Quote | Testimonial text |
| Avatar URL | Optional photo |
| Rating | Star rating (1-5) |
| Active | Show/hide on landing |
| Sort order | Display sequence |

### CRUD operations

- Create new testimonial
- Edit existing
- Delete obsolete entries
- Toggle active without deleting

Public API includes active testimonials in `GET /api/landing`.

## Landing FAQs

**URL:** `/admin/landing-faqs`

FAQ accordion on public landing page (separate from company FAQ knowledge base).

| Field | Description |
|-------|-------------|
| Question | Public FAQ question |
| Answer | Public FAQ answer |
| Sort order | Display order |
| Active | Show/hide |

### Difference from company FAQs

| Type | Audience | Purpose |
|------|----------|---------|
| Landing FAQs | Prospects visiting website | Explain platform pricing, features |
| Company FAQs | WhatsApp customers | Business-specific bot replies |

## Landing page data flow

```
Admin edits testimonials/FAQs/branding
        ↓
Saved to database (platform_settings, testimonials, landing_faqs tables)
        ↓
GET /api/landing + GET /api/plans + GET /api/app-branding
        ↓
Next.js landing page (/) renders dynamically
```

## Pricing section

Plans are managed under [Plans & Billing](plans-billing.md), not this section. Landing page pulls live plan data from `GET /api/plans`.

## Best practices

- Keep testimonials authentic with real business names (with permission)
- Update landing FAQs when pricing or features change
- Align hero text in Platform Settings with current product positioning
