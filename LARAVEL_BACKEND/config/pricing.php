<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default / fallback currency
    |--------------------------------------------------------------------------
    */
    'default_currency' => strtoupper((string) env('PRICING_DEFAULT_CURRENCY', 'KES')),

    /*
    |--------------------------------------------------------------------------
    | Force country (local / staging without Cloudflare)
    |--------------------------------------------------------------------------
    | Set to a 2-letter ISO country code (e.g. KE) to simulate geo detection.
    */
    'force_country' => strtoupper((string) env('PRICING_FORCE_COUNTRY', '')),

    /*
    |--------------------------------------------------------------------------
    | Cookie used when the visitor manually picks a currency
    |--------------------------------------------------------------------------
    */
    'cookie' => 'pricing_currency',
    'cookie_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Supported display / checkout currencies
    |--------------------------------------------------------------------------
    */
    'currencies' => [
        'KES' => [
            'label' => 'Kenyan Shilling',
            'symbol' => 'KSh',
            'decimals' => 0,
        ],
        'USD' => [
            'label' => 'US Dollar',
            'symbol' => '$',
            'decimals' => 0,
        ],
        'NGN' => [
            'label' => 'Nigerian Naira',
            'symbol' => '₦',
            'decimals' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Country (CF-IPCountry) → currency
    |--------------------------------------------------------------------------
    */
    'country_currency' => [
        'KE' => 'KES',
        'NG' => 'NGN',
        'GH' => 'USD',
        'TZ' => 'KES',
        'UG' => 'KES',
        'RW' => 'KES',
        'US' => 'USD',
        'GB' => 'USD',
        'CA' => 'USD',
        'AU' => 'USD',
        'EU' => 'USD',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fixed regional list prices by plan slug
    |--------------------------------------------------------------------------
    | Canonical base amounts. Plan.regional_prices JSON can override per plan.
    | Enterprise / custom plans omit amounts (null).
    */
    'plans' => [
        'starter' => [
            'USD' => 29,
            'KES' => 3799,
            'NGN' => 45000,
        ],
        'professional' => [
            'USD' => 99,
            'KES' => 12999,
            'NGN' => 155000,
        ],
        'enterprise' => [
            'USD' => null,
            'KES' => null,
            'NGN' => null,
        ],
    ],
];
