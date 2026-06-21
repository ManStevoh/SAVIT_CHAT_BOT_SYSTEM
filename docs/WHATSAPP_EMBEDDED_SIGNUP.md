# WhatsApp Embedded Signup (Shared Super Admin App)

This setup enables self-service onboarding where each company connects their own number using Meta's popup flow, while all numbers are managed under one shared platform Meta app.

## 1) Environment variables (backend)

Set these in `LARAVEL_BACKEND/.env`:

- `WHATSAPP_EMBEDDED_APP_ID` - Meta app id used by the frontend SDK popup.
- `WHATSAPP_EMBEDDED_CONFIG_ID` - WhatsApp Embedded Signup configuration id from Meta.
- `WHATSAPP_EMBEDDED_APP_SECRET` - app secret used for code exchange fallback.
- `WHATSAPP_EMBEDDED_REDIRECT_URI` - redirect URI configured in Meta for code exchange.
- `WHATSAPP_DEFAULT_ACCESS_TOKEN` - platform token used to send messages for connected numbers.

Existing required settings still apply:

- `WHATSAPP_WEBHOOK_VERIFY_TOKEN`
- `META_APP_SECRET`

## 2) Meta app checklist (Super Admin)

1. Open your shared Meta app.
2. Add WhatsApp product and configure Embedded Signup.
3. Enable required permissions for WhatsApp management/messaging.
4. Set webhook callback to:
   - `https://YOUR_API_DOMAIN/api/whatsapp/webhook`
5. Subscribe webhook to `messages`.
6. Ensure app mode/review requirements are complete for production onboarding.

## 3) Company onboarding flow (in-app)

1. Company opens `Dashboard -> Settings -> WhatsApp Setup`.
2. Clicks **Connect with Facebook**.
3. Completes popup steps:
   - Login to Facebook
   - Select/create WhatsApp Business Account
   - Add phone number
   - Complete OTP verification
4. App stores `phone_number_id` and `waba_id` to company record.
5. Status changes to Connected.

## 4) API endpoints added

- `GET /api/company/whatsapp/embedded/config`
- `POST /api/company/whatsapp/embedded/complete`

Manual fallback remains available:

- `POST /api/company/whatsapp/connect`

## 5) Troubleshooting

- "Embedded signup is not enabled": missing app/config id env variables.
- "Phone Number ID not received": retry flow; user may have closed popup early.
- Connected but no messages sent: verify queue worker, webhook subscription, and token validity.
