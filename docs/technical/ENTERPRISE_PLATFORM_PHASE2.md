# Enterprise Platform Phase 2 — Implementation Guide

**Verified:** 2026-07-11 · `php artisan platform:verify` · `php artisan test --filter=EnterprisePlatform`

Phase 2 extends existing subscription, billing, and notification plumbing without replacing Stripe/M-Pesa/Paystack checkout flows.

---

## What shipped

### 2a — Subscription entitlements

| Component | Path |
|-----------|------|
| DB column | `plans.entitlements` (JSON) |
| Overrides | `company_entitlement_overrides` |
| Usage meters | `usage_meters` |
| Resolver | `EntitlementService` |
| Back-compat | `PlanLimitService` delegates to `EntitlementService` |
| Meter increment | `ProcessIncomingWhatsAppMessage` → `UsageMeterService` |
| Trial expiry | `ProcessTrialTransitionsJob` (daily 02:00) |

**Seed:** `php artisan db:seed --class=EnterprisePlatformSeeder`

### 2b — Billing ledger

| Component | Path |
|-----------|------|
| Table | `billing_payments` (idempotent on `gateway` + `external_event_id`) |
| Service | `BillingLedgerService` |
| Wired | `MpesaCallbackController`, `StripeWebhookController` (`invoice.paid`) |
| API | `GET /api/company/api-platform/billing-history` |

### 2c — Notification center v1

| Component | Path |
|-----------|------|
| Templates | `notification_templates` (8 seeded keys) |
| Deliveries | `notification_deliveries` |
| Resolver | `NotificationTemplateService` |
| Dispatcher | `NotificationDispatcher` (in-app + email + delivery log) |
| Toggles wired | `notify_security_alerts`, `notify_daily_summary`, `notify_failed_payments`, `notify_usage_alerts` |

### 2d — ABAC policy engine

| Component | Path |
|-----------|------|
| Table | `company_policy_rules` (from ABI foundation) |
| Service | `CompanyPolicyService` (agent approvals) |
| CRUD API | `GET/POST/PATCH/DELETE /api/company/policy-rules` |

### 2e — Audit center

Already shipped in ABI foundation (`audit_events`, `AuditService`). Phase 2 wires policy CRUD + billing + API key lifecycle.

### 2f — Event bus fan-out

| Component | Path |
|-----------|------|
| Outbox | `domain_events` |
| Fan-out | `DomainEventDispatcher` → notifications + webhook queue |
| Webhooks | `WebhookDeliveryService` + `webhook_deliveries` |
| Schedule | `DispatchDomainEventsJob` every minute |

### 2h — API platform v1

| Component | Path |
|-----------|------|
| API keys | `company_api_keys` + `ApiKeyService` |
| Middleware | `api.key` → `AuthenticateApiKey` |
| v1 routes | `GET /api/v1/company/health`, `GET /api/v1/company/orders` |
| Webhooks | `POST /api/company/api-platform/webhooks` |
| Management | `GET/POST/DELETE /api/company/api-platform/keys` |

---

## Verification commands

```bash
php artisan migrate
php artisan db:seed --class=EnterprisePlatformSeeder
php artisan platform:verify
php artisan agent:verify
php artisan test --filter=EnterprisePlatform
php artisan test --filter=CommerceAgent
php artisan route:list --path=api-platform
php artisan route:list --path=v1/company
```

---
