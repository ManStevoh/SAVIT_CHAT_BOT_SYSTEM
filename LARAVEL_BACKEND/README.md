# SAVIT_BOT Laravel API

Backend for the SAVIT_BOT Next.js app. Implements all API endpoints from `../LARAVEL_API.md`.

## Setup

1. **Install dependencies** (already done if you ran composer create-project)
   ```bash
   composer install
   ```

2. **Environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   - Set `APP_URL` to your backend URL (e.g. `http://localhost:8000`).
   - For Next.js CORS, set `FRONTEND_URL=http://localhost:3000` (or your Next.js origin).
   - Database: default is SQLite (`database/database.sqlite`). Create the file if needed:
     ```bash
     touch database/database.sqlite
     ```

3. **Run migrations**
   ```bash
   php artisan migrate
   ```

4. **Seed admin user** (optional)
   ```bash
   php artisan db:seed
   ```
   Creates a super admin: **email** `admin@savit.local`, **password** `password`. Change the password after first login.

5. **Serve**
   ```bash
   php artisan serve
   ```
   API base: `http://localhost:8000/api`

## Auth

- **Login** returns `token` and `user`. The Next.js app should store the token (e.g. `localStorage.setItem('auth_token', data.token)`) and send it as `Authorization: Bearer <token>` on subsequent requests.
- **Register** creates a company and a `company_owner` user. No token is returned; user must log in.
- **Reset password** body must include `email` in addition to `token`, `password`, and `confirmPassword` (Laravel requirement).

## Structure

- **Auth:** `App\Http\Controllers\Api\AuthController`
- **Company:** `App\Http\Controllers\Api\Company\*` (chats, orders, products, faqs, settings, whatsapp)
- **Admin:** `App\Http\Controllers\Api\Admin\*` (companies, users, platform settings, export)
- **Models:** `App\Models\*` (User, Company, Product, Order, Faq, Chat, Message, etc.)
- **Middleware:** `admin` = `EnsureUserIsAdmin` (checks `user->role === 'admin'`)

## Testing with Next.js

In the SAVIT_BOT root:

1. Create `.env.local` with:
   ```
   NEXT_PUBLIC_API_URL=http://localhost:8000
   NEXT_PUBLIC_USE_MOCK_API=false
   ```
2. Run Next.js (`pnpm dev`) and Laravel (`php artisan serve`) and use the app; it will call this API.
