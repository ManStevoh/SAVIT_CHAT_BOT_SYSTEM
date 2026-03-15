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
];
