# API Integration Checklist

This checklist lists every place in the frontend where real API integration has been prepared. Use it to implement backend endpoints and then switch off mock mode (`NEXT_PUBLIC_USE_MOCK_API=false`).

---

## Auth

| Page / Form | Endpoint | Method | Status |
|-------------|----------|--------|--------|
| Login | `POST /api/auth/login` | POST | ✅ Wired (api-actions.login) |
| Register | `POST /api/auth/register` | POST | ✅ Wired (api-actions.register) |
| Forgot Password | `POST /api/auth/forgot-password` | POST | ✅ Wired (api-actions.forgotPassword) |
| Reset Password | `POST /api/auth/reset-password` | POST | ✅ Wired (api-actions.resetPassword); token from query `?token=` |
| Logout | `POST /api/auth/logout` | POST | ✅ Wired (api-actions.logout) |

**Notes:** Register/forgot/reset now call api-actions; validation and error messages displayed. Reset password expects token in URL.

---

## Company Dashboard

### Data (SWR hooks in `lib/api-hooks.ts`)

| Page | Hook | Endpoint | Loading / Empty / Error |
|------|------|----------|------------------------|
| Dashboard (home) | useAnalytics, useOrders, useChats | GET /api/company/analytics, /orders, /chats | ✅ |
| Chats | useChats, useMessages, useCustomers | GET /api/company/chats, /chats/:id/messages | ✅ |
| Orders | useOrders | GET /api/company/orders | ✅ Pagination, filters |
| Customers | useCustomers | GET /api/company/customers | ✅ Pagination, search, empty state |
| Products | useProducts | GET /api/company/products | ✅ |
| FAQ | useFAQs | GET /api/company/faqs | ✅ |
| Analytics | useAnalytics(period) | GET /api/company/analytics?period= | ✅ Charts from API data |
| Subscription | useSubscription | GET /api/company/subscription | ✅ Billing history placeholder |
| Settings | — | GET /api/company/settings (optional) | Form wired to PUT |

### Mutations (api-actions)

| Action | Endpoint | Used On |
|--------|----------|---------|
| sendMessage | POST /api/company/chats/:chatId/messages | Chats page |
| updateOrderStatus | PATCH /api/company/orders/:orderId | Orders page |
| createProduct, updateProduct, deleteProduct | POST/PUT/DELETE /api/company/products | Products page |
| createFAQ, updateFAQ, deleteFAQ | POST/PUT/DELETE /api/company/faqs | FAQ page |
| updateSettings | PUT /api/company/settings | Settings (Business Profile tab) |
| connectWhatsApp | POST /api/company/whatsapp/connect | Settings (WhatsApp tab) |

**Placeholders still using static or hook-driven mock:**

- **Settings:** Team members and WhatsApp numbers use local placeholder arrays; add GET /api/company/team and GET /api/company/whatsapp/numbers when ready.
- **Subscription:** Billing history table uses placeholder list; add GET /api/company/subscription/invoices when ready. Usage (messages, numbers, team) is static; add GET /api/company/subscription/usage if needed.

---

## Admin Dashboard

### Data (SWR hooks)

| Page | Hook | Endpoint | Loading / Empty / Error |
|------|------|----------|------------------------|
| Admin Overview | useAdminOverview, useAdminCompanies | GET /api/admin/overview, /admin/companies | ✅ |
| Revenue | useAdminRevenue(period) | GET /api/admin/revenue?period= | ✅ |
| AI Usage | useAdminAIUsage(period) | GET /api/admin/ai-usage?period= | ✅ |
| Users | useAdminUsers(filters) | GET /api/admin/users | ✅ Search, role filter |
| Companies | useAdminCompanies(filters) | GET /api/admin/companies | ✅ Search, status filter |
| Subscriptions | useAdminSubscriptions(filters) | GET /api/admin/subscriptions | ✅ Search, plan filter |
| Logs | useAdminLogs(filters) | GET /api/admin/logs | ✅ Search, type filter |

### Mutations (api-actions)

| Action | Endpoint | Used On |
|--------|----------|---------|
| updateCompanyStatus | PATCH /api/admin/companies/:id | Companies (dropdown) |
| updateUserStatus | PATCH /api/admin/users/:id | Users (dropdown) |
| updatePlatformSettings | PUT /api/admin/settings | Admin Settings |
| exportData | POST /api/admin/export | Logs (Export button) |

**Charts:** Admin Overview charts (company growth, message volume) use placeholder arrays; extend GET /api/admin/overview to return `companyGrowthData` and `messageVolumeData` when ready.

---

## Landing Page

| Section | Current | API Integration |
|---------|---------|-----------------|
| Trusted companies | Static array | Optional: GET /api/landing/companies or pass as props |
| Testimonials | Static array | Optional: GET /api/landing/testimonials or props |
| Product screenshots | Static | Optional: CMS or props |
| Pricing | Static plans | Optional: GET /api/landing/plans or props |
| How it works | Static steps | Static content OK |
| Features | Static | Static content OK |
| FAQ | Static | Optional: GET /api/landing/faqs or props |

Landing components can accept `data` or `children` props so they can be fed from an API or CMS later without redesign.

---

## Design & Styling

- **Background:** `#0f172a` (slate-900)
- **Cards:** `#111827` (gray-900) via Tailwind card styles
- **Accent:** `#22c55e` (green-500) as primary
- **Responsive:** Grid and layout use Tailwind breakpoints (md, lg). Tables and cards stack on mobile.

All new or updated pages keep the existing dark SaaS style, soft shadows, and rounded cards.

---

## Quick Reference: Where to Add API Calls

1. **Data fetching:** Replace the mock fetcher inside each hook in `lib/api-hooks.ts` with `fetch(apiUrl(...))` or your HTTP client. Keys and response shapes are documented in `lib/mock-data.ts` (interfaces and example endpoint list at top).
2. **Auth:** Already using api-actions; set `NEXT_PUBLIC_API_URL` and `NEXT_PUBLIC_USE_MOCK_API=false`.
3. **Loading states:** Handled in pages (skeleton/spinner when `isLoading && !data`).
4. **Empty states:** Handled (e.g. “No customers yet”, “No data for this period”).
5. **Errors:** Handled with retry or message; use `error` from SWR in each page.

---

## File Map

- `lib/api-client.ts` — base URL, auth token, `apiRequest()`
- `lib/api-actions.ts` — all mutations and auth (login, register, etc.)
- `lib/api-hooks.ts` — all SWR data hooks (company + admin)
- `lib/mock-data.ts` — types and mock arrays; top comment lists example endpoints and response shapes
