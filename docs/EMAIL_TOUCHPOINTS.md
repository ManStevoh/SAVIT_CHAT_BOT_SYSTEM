# Email touchpoints in SAVIT

This document lists every place where the system sends (or is intended to send) emails to users or companies.

## Super Admin: SMTP configuration

- **Where:** Admin → Settings → **Email** tab (`/admin/settings`).
- **What:** Super admin can set SMTP (host, port, encryption, username, password, from address/name), **Save**, and **Send Test Email** to verify delivery.
- **Backend:** Settings stored in `platform_settings`; sending uses `App\Services\MailService` (platform SMTP if configured, otherwise default Laravel mailer from `.env`).
- **APIs:**
  - `GET /api/admin/settings` – load all platform settings (including SMTP; password masked).
  - `PUT /api/admin/settings` – update platform settings (including SMTP fields).
  - `POST /api/admin/settings/test-email` – body `{ "to": "email@example.com" }` – send a test email using current SMTP.

---

## Instances where the system sends (or should send) email

### 1. Password reset (forgot password) — **implemented**

| Item | Detail |
|------|--------|
| **When** | User requests a password reset (e.g. “Forgot password” → enters email). |
| **Backend** | `App\Http\Controllers\Api\AuthController::forgotPassword()` → `Password::sendResetLink($request->only('email'))` → Laravel calls `User::sendPasswordResetNotification($token)`. |
| **Implementation** | `App\Models\User::sendPasswordResetNotification()` overridden to use `MailService`, so email is sent via **platform SMTP** when configured, otherwise default mailer. |
| **Recipient** | The user’s email address. |
| **Content** | Link to frontend `/reset-password?token=...` (FRONTEND_URL from `.env`). |

**Files:**

- `LARAVEL_BACKEND/app/Http/Controllers/Api/AuthController.php` – `forgotPassword()`
- `LARAVEL_BACKEND/app/Models/User.php` – `sendPasswordResetNotification()`
- `LARAVEL_BACKEND/app/Services/MailService.php` – used for sending

---

### 2. Registration “verification” — **not implemented** (message only)

| Item | Detail |
|------|--------|
| **When** | After a company/user registers. |
| **Backend** | `AuthController::register()` returns message: *“Registration successful! Please check your email to verify your account.”* |
| **Implementation** | **No email is sent.** No verification email, no welcome email. The message is misleading. |
| **Recommendation** | Either implement email verification (e.g. `MustVerifyEmail` + verification email) or change the message to e.g. “Registration successful! You can now log in.” |

**Files:**

- `LARAVEL_BACKEND/app/Http/Controllers/Api/AuthController.php` – `register()` (response message only; no `Mail::` or notification)

---

### 3. Login / email verification — **not implemented**

| Item | Detail |
|------|--------|
| **When** | On login. |
| **Implementation** | No email verification step on login. No 2FA or “verify email” flow. |
| **Recipient** | N/A. |

**Files:**

- `LARAVEL_BACKEND/app/Models/User.php` – `MustVerifyEmail` is commented out; no login-time email.

---

### 4. Account creation (by admin or system) — **no email**

| Item | Detail |
|------|--------|
| **When** | Admin creates or invites users; company created. |
| **Implementation** | No “welcome” or “account created” email in the codebase. |
| **Recipient** | N/A. |

**Files:**

- Admin user/company management does not send emails (e.g. `UserController`, `CompanyController`).

---

### 5. Subscription / payment (Stripe) — **implemented**

| Item | Detail |
|------|--------|
| **When** | User subscribes, pays, or gets invoice. |
| **Implementation** | Stripe can send receipts/invoices; the app does not send its own “subscription confirmed” or “payment received” email. |
| **Recipient** | Handled by Stripe if configured there. |

**Files:**

- `LARAVEL_BACKEND/app/Http/Controllers/Api/StripeWebhookController.php` – handles `checkout.session.completed` and `invoice.paid`, calls `MailService`.
- `LARAVEL_BACKEND/app/Services/MailService.php` – `sendSubscriptionConfirmed()`, `sendInvoicePaid()`.
- `LARAVEL_BACKEND/app/Services/StripeService.php` – `handleInvoicePaid()`.

---

### 6. Subscription expiring soon — **implemented**

| Item | Detail |
|------|--------|
| **When** | Scheduled daily (e.g. 09:00). Companies whose subscription is active/trial and `end_date` is in 3 or 7 days receive a reminder. |
| **Implementation** | Command `subscription:expiry-reminders` (option `--days=7,3`). Uses `MailService::sendSubscriptionExpiringSoon()`. |
| **Recipient** | Company email. |

**Files:**

- `LARAVEL_BACKEND/app/Console/Commands/SendSubscriptionExpiryRemindersCommand.php`
- `LARAVEL_BACKEND/bootstrap/app.php` – `withSchedule()` runs the command daily at 09:00.
- `LARAVEL_BACKEND/app/Services/MailService.php` – `sendSubscriptionExpiringSoon()`.

**Cron:** Ensure the scheduler runs (e.g. `* * * * * php /path/to/artisan schedule:run`).

---

### 7. New message notification (company) — **implemented**

| Item | Detail |
|------|--------|
| **When** | A new customer message is saved (WhatsApp webhook receives text or media with caption). |
| **Condition** | Company has `notifications_enabled` (company settings) and a non-empty company email. |
| **Implementation** | After saving the incoming message, `WhatsAppWebhookController` checks company settings and dispatches `SendNewMessageNotificationJob`. The job calls `MailService::sendNewMessageNotification()` with customer name, phone, message preview (first 200 chars), and a “View in dashboard” link to `/dashboard/chats`. |
| **Recipient** | Company email (from `companies.email`). |

**Files:**

- `LARAVEL_BACKEND/app/Http/Controllers/Api/WhatsAppWebhookController.php` – after `Message::create()`, dispatches job when notifications enabled.
- `LARAVEL_BACKEND/app/Jobs/SendNewMessageNotificationJob.php` – loads company/settings, builds frontend URL, sends email via `MailService`.
- `LARAVEL_BACKEND/app/Services/MailService.php` – `sendNewMessageNotification()`.

---

## Summary count

| Type | Implemented | Notes |
|------|-------------|--------|
| **Password reset** | Yes | 1 place: forgot-password → reset link email via `MailService`. |
| **Registration verification** | No | Message says “check your email” but no email is sent. |
| **Login verification** | No | No email on login. |
| **Account creation / welcome** | No | No welcome or “account created” email. |
| **Subscription confirmed** | Yes | After Stripe checkout; plan name and end date. |
| **Invoice / payment received** | Yes | After Stripe `invoice.paid`; invoice id, amount, date. |
| **Subscription expiring soon** | Yes | Daily command; reminders 7 and 3 days before end_date. |
| **New message notification** | Yes | When customer sends message; email to company if notifications enabled. |
| **Admin test email** | Yes | Send Test Email in Admin → Settings → Email. |

User-facing emails are sent via `MailService` (platform SMTP when configured, otherwise default Laravel mailer).

---

## Enabling and testing email

1. **Configure SMTP (super admin):**  
   Admin → Settings → Email → set host, port, encryption, username, password, from address/name → **Save Email Settings**.

2. **Test delivery:**  
   Same tab → enter an email in “Email to receive test” → **Send Test Email**. Check inbox and backend response.

3. **Password reset:**  
   Use “Forgot password” with a valid user email; the reset link email will use the same SMTP if configured.

4. **Fallback:**  
   If no platform SMTP is set, Laravel uses the default mailer (`MAIL_MAILER`, `MAIL_HOST`, etc. in `.env`). With `MAIL_MAILER=log`, messages are written to the log only.
