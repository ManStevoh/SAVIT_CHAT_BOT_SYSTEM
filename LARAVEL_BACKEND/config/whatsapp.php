<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook verification token
    |--------------------------------------------------------------------------
    | Set this in Meta App Dashboard → WhatsApp → Configuration → Callback verify token.
    | Must match exactly for webhook verification (GET).
    */
    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'savit_verify_token'),

    /*
    |--------------------------------------------------------------------------
    | App secret (optional)
    |--------------------------------------------------------------------------
    | Used to verify X-Hub-Signature-256 on incoming webhook POST. Recommended in production.
    */
    'app_secret' => env('META_APP_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Meta Graph API base URL
    |--------------------------------------------------------------------------
    */
    'graph_url' => 'https://graph.facebook.com/v21.0',

    /*
    |--------------------------------------------------------------------------
    | Embedded Signup (shared Super Admin Meta app)
    |--------------------------------------------------------------------------
    | app_id / config_id are used by frontend popup.
    | app_secret + redirect_uri are used when exchanging OAuth "code" server-side.
    | default_access_token is optional fallback token used for all company numbers.
    */
    'embedded_signup_app_id' => env('WHATSAPP_EMBEDDED_APP_ID'),
    'embedded_signup_config_id' => env('WHATSAPP_EMBEDDED_CONFIG_ID'),
    'embedded_signup_app_secret' => env('WHATSAPP_EMBEDDED_APP_SECRET'),
    'embedded_signup_redirect_uri' => env('WHATSAPP_EMBEDDED_REDIRECT_URI'),
    'default_access_token' => env('WHATSAPP_DEFAULT_ACCESS_TOKEN'),
];
