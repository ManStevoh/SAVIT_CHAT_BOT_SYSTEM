<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deploy Secret
    |--------------------------------------------------------------------------
    | Required — no fallback default. If DEPLOY_SECRET is not set, the /deploy
    | route returns 503 rather than exposing a hardcoded credential.
    */
    'secret' => env('DEPLOY_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | IP Allowlist (optional)
    |--------------------------------------------------------------------------
    | Comma-separated IPs. When set, any IP not in the list is rejected with
    | 403 before the password prompt is even shown.
    | Leave empty to allow all IPs.
    | e.g. DEPLOY_ALLOWED_IPS=1.2.3.4,5.6.7.8
    */
    'allowed_ips' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('DEPLOY_ALLOWED_IPS', '')))
    )),

    /*
    |--------------------------------------------------------------------------
    | Deploy Script Path (cPanel)
    |--------------------------------------------------------------------------
    | Absolute path to the server-side deploy shell script.
    | When this file exists and is executable it takes priority over the
    | built-in git + artisan fallback pipeline.
    */
    'script_path' => env('DEPLOY_SCRIPT_PATH', '/home/qkbghwib/deploy'),

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    */
    'lock_path'  => storage_path('framework/deploy.lock'),
    'status_dir' => storage_path('framework/deploy'),
    'audit_log'  => storage_path('logs/deploy.log'),

    /*
    |--------------------------------------------------------------------------
    | Auth Token TTL (minutes)
    |--------------------------------------------------------------------------
    | How long an issued deploy auth token remains valid. Tokens are not
    | single-use so the user can trigger multiple deploys in one session.
    */
    'token_ttl' => (int) env('DEPLOY_TOKEN_TTL', 30),

    /*
    |--------------------------------------------------------------------------
    | Allowed Branches Override (optional)
    |--------------------------------------------------------------------------
    | CSV of branch names. When set, only these branches appear in the UI and
    | the live git discovery is skipped. Leave empty to auto-discover.
    | e.g. DEPLOY_ALLOWED_BRANCHES=main,backend,staging
    */
    'allowed_branches' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('DEPLOY_ALLOWED_BRANCHES', '')))
    )),

    /*
    |--------------------------------------------------------------------------
    | Background Mode
    |--------------------------------------------------------------------------
    | When true, deploys are spawned as a background artisan process and
    | the frontend polls /deploy/status/{token} for live updates.
    | When false, deploys run synchronously (simpler but subject to PHP
    | max_execution_time limits on shared hosting).
    */
    'background' => (bool) env('DEPLOY_BACKGROUND', true),

];
