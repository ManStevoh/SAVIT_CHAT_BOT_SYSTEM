<?php

return [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'trial_days' => (int) env('STRIPE_TRIAL_DAYS', 14),
    'currency' => env('STRIPE_CURRENCY', 'usd'),
];
