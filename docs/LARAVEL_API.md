# Laravel API contract for ESSEM_BOT

Use this as a checklist when building your Laravel backend. The Next.js app sends `Accept: application/json` and expects JSON responses. Use **Laravel Sanctum** (or your auth) and ensure CORS allows your Next.js origin.

---

## Auth (no auth required)

| Method | Route | Body | Response (success) |
|--------|--------|------|--------------------|
| POST | `/api/auth/login` | `email`, `password`, `rememberMe?` | `{ success: true, token?, user: User }` — Laravel backend returns `token`; Next.js stores it as `auth_token` so `api-client` sends `Authorization: Bearer` |
| POST | `/api/auth/register` | `companyName`, `name`, `email`, `phone`, `password`, `confirmPassword`, `acceptTerms` | `{ success: true, message?: string }` |
| POST | `/api/auth/forgot-password` | `email` | `{ success: true, message?: string }` |
| POST | `/api/auth/reset-password` | `token`, `email`, `password`, `confirmPassword` (Laravel requires `email`) | `{ success: true, message?: string }` |
| POST | `/api/auth/logout` | — | `{ success: true }` (auth required) |

---

## Company (auth required: company user)

| Method | Route | Body | Response (success) |
|--------|--------|------|--------------------|
| POST | `/api/company/chats/:chatId/messages` | `content` | `{ success: true, message?: string }` |
| POST | `/api/company/chats/start` | `phone`, `name?` | `{ success: true, created: bool, chat: Chat }` — find-or-create chat by phone (mobile add-contact) |
| PATCH | `/api/company/orders/:orderId` | `status` | `{ success: true, message?: string }` |
| POST | `/api/company/products` | JSON: `name`, `description`, `price`, `category`, `stock` — or multipart with `image` | `{ success: true, product?: Product, message?: string }` |
| PUT | `/api/company/products/:productId` | `name?`, `description?`, `price?`, `category?`, `stock?` | `{ success: true, message?: string }` |
| DELETE | `/api/company/products/:productId` | — | `{ success: true, message?: string }` |
| POST | `/api/company/faqs` | `question`, `answer`, `category`, `keywords[]` | `{ success: true, faq?: FAQ, message?: string }` |
| PUT | `/api/company/faqs/:faqId` | partial + `isActive?` | `{ success: true, message?: string }` |
| DELETE | `/api/company/faqs/:faqId` | — | `{ success: true, message?: string }` |
| PUT | `/api/company/settings` | JSON or multipart: `companyName?`, `email?`, `phone?`, `logo?`, `whatsappNumber?`, `aiGreeting?`, `aiTone?`, `autoReplyEnabled?`, `notificationsEnabled?` | `{ success: true, message?: string }` |
| POST | `/api/company/whatsapp/connect` | `phoneNumber` | `{ success: true, qrCode?: string, message?: string }` |

---

## Admin (auth required: super admin)

| Method | Route | Body | Response (success) |
|--------|--------|------|--------------------|
| PATCH | `/api/admin/companies/:companyId` | `status` | `{ success: true, message?: string }` |
| PATCH | `/api/admin/users/:userId` | `status` | `{ success: true, message?: string }` |
| PUT | `/api/admin/settings` | `platformName?`, `supportEmail?`, `maintenanceMode?`, `aiModel?`, `maxTokensPerRequest?`, `rateLimitPerMinute?` | `{ success: true, message?: string }` |
| POST | `/api/admin/export` | `dataType` (`companies` \| `users` \| `subscriptions` \| `revenue`), `format` (`csv` \| `json` \| `xlsx`) | `{ success: true, downloadUrl?: string, message?: string }` |

---

## Error responses

- Use HTTP 4xx/5xx and JSON body. The frontend reads `message` or `errors` and shows them.
- Example: `422` with `{ message: "Validation failed.", errors: { email: ["The email field is required."] } }` — the client will surface the message or first error.

---

## Env on Next.js

- `NEXT_PUBLIC_API_URL` = your Laravel base URL (e.g. `http://localhost:8000`).
- `NEXT_PUBLIC_USE_MOCK_API=false` to call Laravel instead of mock.

After implementing these routes in Laravel, set the env and the app will call your backend.
