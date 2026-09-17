<?php

return [
    'enabled' => filter_var(env('MERCHANT_LIFECYCLE_ENABLED', true), FILTER_VALIDATE_BOOL),

    /*
    | Delay in seconds after registration before each follow-up may send.
    | Welcome is immediate (register). Verified is immediate (email confirm).
    */
    'delays' => [
        'add_first_product' => (int) env('MERCHANT_LIFECYCLE_DELAY_PRODUCT', 4 * 3600),
        'connect_whatsapp' => (int) env('MERCHANT_LIFECYCLE_DELAY_WHATSAPP', 24 * 3600),
        'enable_payments' => (int) env('MERCHANT_LIFECYCLE_DELAY_PAYMENTS', 24 * 3600),
        'share_store' => (int) env('MERCHANT_LIFECYCLE_DELAY_SHARE', 12 * 3600),
        'marketing_growth' => (int) env('MERCHANT_LIFECYCLE_DELAY_GROWTH', 3 * 86400),
        'marketing_tips' => (int) env('MERCHANT_LIFECYCLE_DELAY_TIPS', 7 * 86400),
    ],

    'whatsapp' => [
        'phone_number_id' => env('PLATFORM_WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('PLATFORM_WHATSAPP_ACCESS_TOKEN'),
        'template_language' => env('PLATFORM_WHATSAPP_TEMPLATE_LANG', 'en'),
        'templates' => [
            'welcome' => env('PLATFORM_WHATSAPP_TPL_WELCOME', 'merchant_welcome'),
            'verified' => env('PLATFORM_WHATSAPP_TPL_VERIFIED', 'merchant_verified'),
            'add_first_product' => env('PLATFORM_WHATSAPP_TPL_PRODUCT', 'merchant_add_product'),
            'connect_whatsapp' => env('PLATFORM_WHATSAPP_TPL_CONNECT', 'merchant_connect_whatsapp'),
            'enable_payments' => env('PLATFORM_WHATSAPP_TPL_PAYMENTS', 'merchant_enable_payments'),
            'share_store' => env('PLATFORM_WHATSAPP_TPL_SHARE', 'merchant_share_store'),
            'marketing_growth' => env('PLATFORM_WHATSAPP_TPL_GROWTH', 'merchant_marketing_growth'),
            'marketing_tips' => env('PLATFORM_WHATSAPP_TPL_TIPS', 'merchant_marketing_tips'),
        ],
    ],
];
