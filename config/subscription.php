<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default plan for new companies (free trial)
    |--------------------------------------------------------------------------
    | Slug of the plan assigned when a company registers. Must exist in plans table.
    */
    'default_plan_slug' => env('SUBSCRIPTION_DEFAULT_PLAN_SLUG', 'starter'),

    /*
    |--------------------------------------------------------------------------
    | Default trial length (days) for new companies
    |--------------------------------------------------------------------------
    */
    'default_trial_days' => (int) env('SUBSCRIPTION_DEFAULT_TRIAL_DAYS', 14),
];
