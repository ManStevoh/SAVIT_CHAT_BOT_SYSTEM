# Pending Items – Full Inspection

Inspection date: 2025-03-15. This doc lists what is **not yet done** or **optional / documented gaps** so you can decide what to do next.

**Implemented 2025-03-15:** Hand back to bot (API + UI), registration welcome/email verification (MustVerifyEmail + resend), per-plan message limit enforcement in `ProcessIncomingWhatsAppMessage`.

---

## 1. Bot flow (BOT_FLOW_IMPROVEMENTS.md)

All checklist items in `docs/BOT_FLOW_IMPROVEMENTS.md` are **done** (P0–P3 and quick wins). **“Hand back to bot”** is now implemented:

| Item | Status | Notes |
|------|--------|--------|
| **“Hand back to bot”** | **Done** | `POST /api/company/chats/:chatId/hand-back` clears `agent_handling_at`. Dashboard chat header shows “Hand back to bot” when an agent is handling; GET chats includes `agentHandlingAt`. |

---

## 2. Email touchpoints (docs/EMAIL_TOUCHPOINTS.md)

| Item | Status | Notes |
|------|--------|--------|
| **Registration “verification”** | **Done** | After register, `User::sendEmailVerificationNotification()` sends welcome+verify email via `MailService`. Link goes to `GET /api/auth/verify-email` (signed); backend marks verified and redirects to frontend `/login?verified=1`. |
| **Login / email verification** | **Done** | `User` implements `MustVerifyEmail`. Login returns 403 with `code: 'email_not_verified'` if not verified; frontend shows “Resend verification email”. `POST /api/auth/resend-verification` resends the link. |

---

## 3. Subscription & limits (from BOT_FLOW_IMPROVEMENTS §7)

| Item | Status | Notes |
|------|--------|--------|
| **Disable bot when subscription expired** | Done | `ProcessIncomingWhatsAppMessage` checks active subscription; sends “service temporarily unavailable” when none. |
| **Message / usage limits (per plan)** | **Done** | `PlanLimitService` centralizes plan limits. `ProcessIncomingWhatsAppMessage` calls `PlanLimitService::isWithinMessageLimit($company)`; when over limit, skips auto-reply and notifies company (no extra message sent). `SubscriptionController::usage` uses the same service. |

---

## 4. Frontend / API (AUDIT_PLAN_VS_IMPLEMENTATION.md)

- **GET /api/company/settings** – Implemented (backend and frontend use it).

**Optional areas (Team, WhatsApp, invoices, landing, admin charts):** When `NEXT_PUBLIC_USE_MOCK_API=false`, the frontend already calls the real API for all of these. No extra implementation is required.

| Area | Frontend hook | Where used | Backend route |
|------|----------------|------------|----------------|
| Team members | `useCompanyTeam()` | Dashboard → Settings | `GET /api/company/team` |
| WhatsApp numbers | `useWhatsAppNumbers()` | Dashboard → Settings | `GET /api/company/whatsapp/numbers` |
| Subscription invoices | `useSubscriptionInvoices()` | Dashboard → Subscription | `GET /api/company/subscription/invoices` |
| Subscription usage | `useSubscriptionUsage()` | Dashboard → Subscription | `GET /api/company/subscription/usage` |
| Landing (testimonials, trusted companies, FAQ) | `useLanding()` | Public landing page | `GET /api/landing` |
| Admin testimonials | `useAdminTestimonials()` | Admin → Testimonials | `GET/POST/PUT/DELETE /api/admin/testimonials` |
| Admin landing FAQ | `useAdminLandingFaqs()` | Admin → Landing FAQ | `GET/POST/PUT/DELETE /api/admin/landing-faqs` |
| Admin overview charts | `useAdminOverview()` | Admin dashboard | `GET /api/admin/overview` (returns `companyGrowthData`, `messageVolumeData`) |

Landing trusted companies are managed in **Admin → Settings → Landing**. Pricing comes from **GET /api/plans** (public).

No **blocking** pending items for replacing mock and going live.

---

## 5. Migrations

Ensure all migrations have been run:

```bash
cd LARAVEL_BACKEND && php artisan migrate:status
```

If any are “Pending”, run:

```bash
php artisan migrate
```

Notable migrations for bot improvements:

- `2025_03_15_190000_add_bot_improvements_to_chats_and_company_settings.php` (e.g. `agent_handling_at`, `fallback_message`, `away_message`, `working_hours`)
- `2025_03_15_200000_add_conversation_state_to_chats.php` (conversation/order flow)

---

## 6. Summary table

| Area | Status |
|------|--------|
| Hand back to bot | Done (API + UI) |
| Registration + verification email | Done (welcome email + signed verify link) |
| Login email verification | Done (403 + resend) |
| Per-plan message limit | Done (`PlanLimitService` + job check) |
| Migrations | Run `php artisan migrate` (includes `verify_existing_users_email` for existing users). |

**Bottom line:** The items above are implemented. Run `php artisan migrate` so existing users get `email_verified_at` set and are not locked out.
