<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook verification token (fallback when not set in Super Admin UI)
    |--------------------------------------------------------------------------
    */
    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | App secret (fallback when not set in Super Admin UI)
    |--------------------------------------------------------------------------
    */
    'app_secret' => env('META_APP_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Embedded Signup (fallback when not set in Super Admin → Integrations)
    |--------------------------------------------------------------------------
    */
    'embedded_signup_app_id' => env('WHATSAPP_EMBEDDED_APP_ID'),
    'embedded_signup_config_id' => env('WHATSAPP_EMBEDDED_CONFIG_ID'),
    'embedded_signup_app_secret' => env('WHATSAPP_EMBEDDED_APP_SECRET'),
    'embedded_signup_redirect_uri' => env('WHATSAPP_EMBEDDED_REDIRECT_URI'),
];
