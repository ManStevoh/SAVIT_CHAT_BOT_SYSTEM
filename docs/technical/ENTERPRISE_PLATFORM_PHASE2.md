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
