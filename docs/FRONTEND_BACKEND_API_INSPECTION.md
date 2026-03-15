# Frontend–Backend API Inspection Report

This document compares the frontend API usage (Next.js) with the Laravel backend implementation.

---

## Summary

| Area | Mutations (POST/PUT/PATCH/DELETE) | GET (list/show) |
|------|-----------------------------------|------------------|
| **Auth** | ✅ All implemented | N/A |
| **Company** | ✅ All implemented | ✅ All implemented |
| **Admin** | ✅ All implemented | ✅ All implemented |

**Verdict:** All frontend API endpoints (mutations and GET) are implemented in the backend. When `NEXT_PUBLIC_USE_MOCK_API=false` and `NEXT_PUBLIC_API_URL` is set, `lib/api-hooks.ts` calls the Laravel API; otherwise it uses mock data.

---

## 1. Auth — ✅ Fully aligned

| Frontend (api-actions) | Backend (routes + AuthController) | Status |
|------------------------|-----------------------------------|--------|
| `POST /api/auth/login` | ✅ Same | Body: `email`, `password`. Response: `success`, `token`, `user` (camelCase). |
| `POST /api/auth/register` | ✅ Same | Body: `companyName`, `name`, `email`, `phone`, `password`, `confirmPassword` (→ `password_confirmation`), `acceptTerms`. |
| `POST /api/auth/forgot-password` | ✅ Same | Body: `email`. |
| `POST /api/auth/reset-password` | ✅ Same | Body: `token`, `email`, `password`, `confirmPassword`. Controller merges `confirmPassword` → `password_confirmation`. |
| `POST /api/auth/logout` | ✅ Same (auth:sanctum) | Response: `success`. |

---

## 2. Company dashboard — Mutations ✅ implemented, GET ❌ missing

### Mutations (api-actions → backend)

| Action | Frontend | Backend | Status |
|--------|----------|---------|--------|
| sendMessage | `POST /api/company/chats/:chatId/messages` body `{ content }` | ✅ ChatMessageController::store($chatId), validates `content` | ✅ |
| updateOrderStatus | `PATCH /api/company/orders/:orderId` body `{ status }` | ✅ OrderController::updateStatus(Order $order), validates `status` | ✅ |
| createProduct | `POST /api/company/products` JSON or FormData (name, description, price, category, stock, image?) | ✅ ProductController::store(), supports file or JSON | ✅ |
| updateProduct | `PUT /api/company/products/:productId` | ✅ ProductController::update(Product $product) | ✅ |
| deleteProduct | `DELETE /api/company/products/:productId` | ✅ ProductController::destroy(Product $product) | ✅ |
| createFAQ | `POST /api/company/faqs` body question, answer, category, keywords | ✅ FaqController::store() | ✅ |
| updateFAQ | `PUT /api/company/faqs/:faqId` body partial + **isActive** | ✅ FaqController::update(Faq $faq) | ⚠️ See FAQ note below |
| deleteFAQ | `DELETE /api/company/faqs/:faqId` | ✅ FaqController::destroy(Faq $faq) | ✅ |
| updateSettings | `PUT /api/company/settings` (JSON or FormData with logo) | ✅ SettingsController::update(), accepts companyName, email, phone, logo, whatsappNumber, aiGreeting, aiTone, autoReplyEnabled, notificationsEnabled | ✅ |
| connectWhatsApp | `POST /api/company/whatsapp/connect` body `{ phoneNumber }` | ✅ WhatsAppController::connect() | ✅ |

**FAQ update note:** Frontend sends `isActive` (camelCase). Backend validates `is_active` (snake_case). So `isActive` is never applied. Backend was updated to accept `isActive` and map it to `is_active` for consistency.

### GET endpoints (api-hooks) — ✅ Implemented

| Hook | Endpoint | Backend controller |
|------|----------|---------------------|
| useChats | `GET /api/company/chats` | ChatController::index |
| useMessages | `GET /api/company/chats/:chatId/messages` | ChatMessageController::index |
| useOrders | `GET /api/company/orders` | OrderController::index |
| useCustomers | `GET /api/company/customers` | CustomerController::index |
| useProducts | `GET /api/company/products` | ProductController::index |
| useFAQs | `GET /api/company/faqs` | FaqController::index |
| useAnalytics | `GET /api/company/analytics` | AnalyticsController::index |
| useSubscription | `GET /api/company/subscription` | SubscriptionController::show |

Frontend `lib/api-hooks.ts` calls these via `apiRequest` when `useMockApi()` is false.

---

## 3. Admin — Mutations ✅ implemented, GET ❌ missing

### Mutations (api-actions → backend)

| Action | Frontend | Backend | Status |
|--------|----------|---------|--------|
| updateCompanyStatus | `PATCH /api/admin/companies/:companyId` body `{ status }` | ✅ CompanyController::updateStatus(Company $company) | ✅ |
| updateUserStatus | `PATCH /api/admin/users/:userId` body `{ status }` | ✅ UserController::updateStatus(User $user) | ✅ |
| updatePlatformSettings | `PUT /api/admin/settings` | ✅ PlatformSettingsController::update() | ✅ |
| exportData | `POST /api/admin/export` body `{ dataType, format }` | ✅ ExportController::export() | ✅ |

### GET endpoints (api-hooks) — ✅ Implemented

| Hook | Endpoint | Backend controller |
|------|----------|---------------------|
| useAdminOverview | `GET /api/admin/overview` | OverviewController::index |
| useAdminCompanies | `GET /api/admin/companies` | CompanyController::index |
| useAdminUsers | `GET /api/admin/users` | UserController::index |
| useAdminSubscriptions | `GET /api/admin/subscriptions` | SubscriptionController::index |
| useAdminRevenue | `GET /api/admin/revenue` | RevenueController::index |
| useAdminAIUsage | `GET /api/admin/ai-usage` | AIUsageController::index |
| useAdminLogs | `GET /api/admin/logs` | LogController::index (SystemLog model) |

---

## 4. Route parameter naming

Laravel uses route model binding:

- `orders/{order}` → frontend sends `orderId` in path → ✅ (ID in URL resolves to `Order`).
- `companies/{company}`, `users/{user}`, `products/{product}`, `faqs/{faq}` → frontend sends `companyId`, `userId`, `productId`, `faqId` in path → ✅.

No changes needed for parameter naming.

---

## 5. Recommendations

1. **Mutations:** No change needed; backend matches frontend.
2. **GET endpoints:** Implemented; set `NEXT_PUBLIC_USE_MOCK_API=false` and `NEXT_PUBLIC_API_URL` to your Laravel URL to use real data.
3. **Logs:** Admin logs use the `system_logs` table; seed or write logs via your app if you want non-empty data.

---

## 6. Changes made (inspection + full implementation)

- **Backend:** `FaqController::update` accepts `isActive` and maps to `is_active`.
- **Backend:** All company GET endpoints added (ChatController, ChatMessageController::index, OrderController::index, CustomerController, ProductController::index, FaqController::index, AnalyticsController, SubscriptionController).
- **Backend:** All admin GET endpoints added (OverviewController, CompanyController::index, UserController::index, SubscriptionController, RevenueController, AIUsageController, LogController); `system_logs` migration and SystemLog model created.
- **Frontend:** `lib/api-hooks.ts` uses `apiRequest` for all hooks when `useMockApi()` is false; `buildPath()` added for query params.
