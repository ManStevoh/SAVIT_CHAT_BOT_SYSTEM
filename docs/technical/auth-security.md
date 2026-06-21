---
title: Auth & Security
parent: Technical Documentation
nav_order: 10
---

# Authentication & Security

## Authentication: Laravel Sanctum

### Token-based API auth

1. Client POST `/api/auth/login` with credentials
2. Server returns plain-text Bearer token
3. Client stores in `localStorage` as `auth_token`
4. All protected requests include: `Authorization: Bearer {token}`
5. Logout POST `/api/auth/logout` revokes current token

Tokens stored in `personal_access_tokens` table.

### Stateful domains (optional)

`SANCTUM_STATEFUL_DOMAINS` configured for SPA cookie auth. Primary implementation uses Bearer tokens in localStorage.

## Authorization

### Role check

```php
// EnsureUserIsAdmin middleware
$user->role === 'admin'
```

### Subscription check

```php
// EnsureSubscriptionActive middleware
$company->hasActiveSubscription()
```

Applied to `/api/company/*` except subscription renewal routes.

## Email verification

- Users implement `MustVerifyEmail`
- Verification link: `GET /api/auth/verify-email?id=&hash=&signature=`
- Signed URL prevents tampering

## Password reset

- Token generated, stored hashed
- Email contains link to frontend `/reset-password?token=&email=`
- POST `/api/auth/reset-password` validates token

## CORS

`config/cors.php`:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => [env('FRONTEND_URL')],
'supports_credentials' => true,
```

Production must set matching `FRONTEND_URL` on backend and `NEXT_PUBLIC_API_URL` on frontend.

## Webhook security

| Webhook | Verification method |
|---------|---------------------|
| WhatsApp | `X-Hub-Signature-256` HMAC with Meta App Secret |
| Stripe | `Stripe-Signature` with webhook secret |
| Paystack | `X-Paystack-Signature` HMAC SHA512 |
| M-Pesa | Safaricom callback structure validation |

WhatsApp GET verification uses shared verify token (not secret, but required to match).

## Data encryption

| Data | Method |
|------|--------|
| Passwords | bcrypt (Laravel default) |
| WhatsApp tokens | Laravel Crypt (APP_KEY) |
| Payment gateway secrets | Encrypted in DB JSON |
| OAuth tokens | Encrypted at rest |
| Attribution IPs | Hashed (Growth Engine GDPR) |

**Critical:** Protect `APP_KEY` — loss prevents decrypting stored tokens.

## Multi-tenancy isolation

- All company queries scoped by `company_id` from authenticated user
- WhatsApp webhook resolves company by `phone_number_id` (not user input)
- Admin routes explicitly check admin role
- Impersonation creates audit log entries

## Input validation

- Form requests and inline validation on all POST/PUT endpoints
- File uploads: type and size limits on product/post images
- CSV import: row-level validation with error reporting

## Rate limiting

Laravel default `ThrottleRequests` on API routes. Adjust in `bootstrap/app.php` or route groups if needed.

## HTTPS requirements

- Production webhook URLs must be HTTPS (Meta requirement)
- Stripe webhooks require HTTPS
- Vercel frontend enforces HTTPS

## Security checklist (production)

- [ ] Change default admin password
- [ ] `APP_DEBUG=false`
- [ ] Strong `APP_KEY` (auto-generated)
- [ ] Meta App Secret configured
- [ ] Stripe webhook secret configured
- [ ] SMTP credentials secured
- [ ] Database credentials not in git
- [ ] `.env` excluded from version control
- [ ] Queue worker runs as non-root user
- [ ] File upload directory not executable
- [ ] Regular dependency updates (`composer update`, `npm audit`)

## Impersonation audit

Admin impersonation endpoints log to `system_logs` with admin user ID and target. Review in Admin → Logs.

## Session fixation

Sanctum tokens are stateless Bearer tokens — not vulnerable to session fixation in traditional sense. Token rotation on password change recommended (implement if needed).

## XSS / CSRF

- Next.js escapes rendered content by default
- API uses Bearer tokens (CSRF not applicable for API calls)
- Sanctum CSRF cookie available if switching to cookie auth
