---
title: API Reference
parent: Technical Documentation
nav_order: 5
---

# API Reference

**Base URL:** `https://essemchat.essemglobalsolutions.com/api`  
**Auth:** Bearer token via `Authorization: Bearer {token}` header  
**Content-Type:** `application/json` (multipart for file uploads)

## Public endpoints (no auth)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/plans` | Active subscription plans for landing |
| GET | `/landing` | Testimonials, landing FAQs, hero content |
| GET | `/app-branding` | Platform name, logo, colors |

## Webhooks (signature verified, no Bearer auth)

| Method | Path | Source |
|--------|------|--------|
| GET | `/whatsapp/webhook` | Meta verification |
| POST | `/whatsapp/webhook` | Meta incoming messages |
| POST | `/stripe/webhook` | Stripe events |
| POST | `/mpesa/callback` | Safaricom STK result |
| POST | `/paystack/webhook` | Paystack events |

## Auth endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/auth/register` | No | Create user + company |
| POST | `/auth/login` | No | Returns token + user |
| POST | `/auth/logout` | Yes | Revoke token |
| POST | `/auth/forgot-password` | No | Send reset email |
| POST | `/auth/reset-password` | No | Reset with token |
| POST | `/auth/resend-verification` | No | Resend verify email |
| GET | `/auth/verify-email` | No | Email verification link |

### Register request body

```json
{
  "name": "Jane Doe",
  "email": "jane@business.com",
  "password": "secret",
  "password_confirmation": "secret",
  "company_name": "Jane's Salon",
  "company_category": "salon"
}
```

### Login response

```json
{
  "token": "1|abc...",
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@business.com",
    "role": "company_owner",
    "company_id": 1
  }
}
```

## Company endpoints

**Middleware:** `auth:sanctum`, `subscription.active`

### Chats & messages

| Method | Path | Description |
|--------|------|-------------|
| GET | `/company/chats` | List chats |
| POST | `/company/chats/{id}/hand-back` | Return chat to bot |
| GET | `/company/chats/{id}/messages` | Message thread |
| POST | `/company/chats/{id}/messages` | Send manual reply |

### Orders

| Method | Path | Description |
|--------|------|-------------|
| GET | `/company/orders` | List orders |
| GET | `/company/orders/{id}` | Order detail |
| POST | `/company/orders` | Create manual order |
| PATCH | `/company/orders/{id}` | Update status/payment |

### Products

| Method | Path | Description |
|--------|------|-------------|
| GET | `/company/products` | List products |
| POST | `/company/products` | Create product |
| POST | `/company/products/{id}` | Update (multipart) |
| PUT | `/company/products/{id}` | Update (JSON) |
| DELETE | `/company/products/{id}` | Delete product |
| POST | `/company/products/{id}/variants` | Add variant |
| PUT | `/company/product-variants/{id}` | Update variant |
| DELETE | `/company/product-variants/{id}` | Delete variant |
| POST | `/company/products/{id}/images` | Upload product image |
| POST | `/company/product-variants/{id}/images` | Upload variant image |

### FAQs

| Method | Path | Description |
|--------|------|-------------|
| GET | `/company/faqs` | List FAQs |
| POST | `/company/faqs` | Create FAQ |
| PUT | `/company/faqs/{id}` | Update FAQ |
| DELETE | `/company/faqs/{id}` | Delete FAQ |

### Analytics & customers

| Method | Path | Description |
|--------|------|-------------|
| GET | `/company/analytics` | Business analytics |
| GET | `/company/customers` | Customer list |
| GET | `/company/customers/stats` | Customer aggregates |

### Settings & WhatsApp

| Method | Path | Description |
|--------|------|-------------|
| GET | `/company/settings` | Get all settings |
| PUT | `/company/settings` | Update settings |
| GET | `/company/whatsapp/status` | Connection status |
| POST | `/company/whatsapp/connect` | Manual connect |
| POST | `/company/whatsapp/disconnect` | Disconnect |
| GET | `/company/whatsapp/embedded/config` | Embedded signup config |
| POST | `/company/whatsapp/embedded/complete` | Complete embedded flow |

### Subscription & billing

| Method | Path | Description |
|--------|------|-------------|
| GET | `/company/subscription` | Current subscription |
| GET | `/company/subscription/usage` | Usage meters |
| GET | `/company/subscription/invoices` | Invoice list |
| POST | `/company/checkout` | Stripe checkout session |
| POST | `/company/billing-portal` | Stripe portal URL |
| POST | `/company/mpesa/initiate` | M-Pesa STK for subscription |
| POST | `/company/paystack/initialize` | Paystack subscription |

### Growth Engine (selected)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/company/growth/analytics` | Executive dashboard |
| GET/POST | `/company/growth/posts` | List/create posts |
| POST | `/company/growth/content/generate` | AI generate |
| POST | `/company/growth/posts/{id}/publish` | Publish now |
| GET | `/company/growth/social-accounts` | Connected platforms |
| GET | `/company/growth/oauth/{platform}/authorize` | OAuth redirect URL |
| GET | `/company/growth/intelligence/weekly-brief` | AI brief |

Full Growth API list: see [Growth Engine](growth-engine.md).

### Import/export

| Method | Path | Description |
|--------|------|-------------|
| POST | `/company/import/products` | CSV import |
| POST | `/company/import/faqs` | CSV import |
| POST | `/company/export` | Request export |
| GET | `/company/export/download/{file}` | Download export |

## Admin endpoints

**Middleware:** `auth:sanctum`, `admin`

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/overview` | Platform dashboard |
| GET | `/admin/companies` | List companies |
| PUT | `/admin/companies/{id}` | Update company |
| PATCH | `/admin/companies/{id}` | Update status |
| GET | `/admin/users` | List users |
| PATCH | `/admin/users/{id}` | Update user status |
| POST | `/admin/users/{id}/reset-password` | Trigger reset |
| GET/POST/PUT/DELETE | `/admin/plans` | Plan CRUD |
| GET | `/admin/subscriptions` | All subscriptions |
| GET | `/admin/revenue` | Revenue metrics |
| GET | `/admin/ai-usage` | OpenAI usage |
| GET | `/admin/logs` | System logs |
| GET/PUT | `/admin/payment-gateways/{slug}` | Gateway config |
| GET/PUT/POST | `/admin/settings` | Platform settings |
| POST | `/admin/impersonate/user/{id}` | Impersonate user |
| POST | `/admin/impersonate/company/{id}` | Impersonate company |
| GET | `/admin/growth-portfolio` | Cross-tenant growth |
| GET | `/admin/system-health` | Health checks |

## Error responses

| Status | Meaning |
|--------|---------|
| 401 | Missing or invalid token |
| 403 | Wrong role or inactive subscription |
| 422 | Validation errors |
| 404 | Resource not found |
| 429 | Rate limit (if configured) |
| 500 | Server error |

Validation error format:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

## Rate limiting

Laravel default API throttle applies. WhatsApp webhook has no throttle (Meta retries).

## CORS

Configured in `config/cors.php`:

- Allowed origins: `FRONTEND_URL` and Sanctum stateful domains
- Credentials supported for cookie auth (primary auth is Bearer token)
