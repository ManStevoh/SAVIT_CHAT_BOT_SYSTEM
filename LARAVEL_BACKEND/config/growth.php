<?php

return [
  'platforms' => ['facebook', 'instagram', 'linkedin', 'tiktok', 'twitter', 'whatsapp', 'website', 'email'],

  'agent_types' => ['research', 'content', 'posting', 'analytics', 'crm', 'strategy'],

  'referral_prefix' => 'ref:',

  'limits' => [
    'starter' => ['ai_posts_per_month' => 20, 'ai_images_per_month' => 10, 'platforms' => 1, 'growth_enabled' => true],
    'professional' => ['ai_posts_per_month' => 100, 'ai_images_per_month' => 50, 'platforms' => 3, 'growth_enabled' => true],
    'enterprise' => ['ai_posts_per_month' => 500, 'ai_images_per_month' => 200, 'platforms' => 10, 'growth_enabled' => true],
  ],

  'portfolio_prune_days' => (int) env('GROWTH_PORTFOLIO_PRUNE_DAYS', 90),

  'integrations' => [
    'ga4' => [
      'enabled' => (bool) env('GROWTH_GA4_ENABLED', false),
      'measurement_id' => env('GROWTH_GA4_MEASUREMENT_ID'),
      'api_secret' => env('GROWTH_GA4_API_SECRET'),
    ],
    'email' => [
      'enabled' => (bool) env('GROWTH_EMAIL_SYNC_ENABLED', false),
      'provider' => env('GROWTH_EMAIL_PROVIDER', 'mailchimp'),
    ],
  ],

  'meta' => [
    'graph_url' => env('META_GRAPH_URL', 'https://graph.facebook.com/v21.0'),
  ],

  'default_currency' => env('GROWTH_DEFAULT_CURRENCY', 'KES'),

  'crm' => [
    'hours_quiet' => (int) env('GROWTH_CRM_HOURS_QUIET', 24),
    'max_follow_ups' => (int) env('GROWTH_CRM_MAX_FOLLOW_UPS', 2),
    'payment_recovery_hours' => (int) env('GROWTH_CRM_PAYMENT_RECOVERY_HOURS', 48),
    'industry_templates' => [
      'retail' => "Hi {name}! The items you asked about are still available. Reply to complete your order — we'd love to help!",
      'restaurant' => "Hi {name}! Still thinking about your order? Reply and we'll get it ready for you.",
      'services' => "Hi {name}! Just following up on your inquiry. Reply anytime and we'll assist you.",
      'default' => "Hi {name}! Just checking in — did you still want help with your inquiry? Reply anytime and we'll assist you.",
    ],
    'payment_recovery_templates' => [
      'default' => "Hi {name}! Your order #{order} is waiting — complete payment anytime and we'll confirm it right away.",
    ],
  ],

  'prediction' => [
    'min_published_posts' => (int) env('GROWTH_MIN_PUBLISHED_POSTS', 3),
    'min_clicks' => (int) env('GROWTH_MIN_CLICKS', 10),
  ],

  'industry_clusters' => ['retail', 'restaurant', 'services', 'other'],

  'oauth' => [
    'meta' => [
      'client_id' => env('GROWTH_META_APP_ID'),
      'client_secret' => env('GROWTH_META_APP_SECRET'),
    ],
    'linkedin' => [
      'client_id' => env('LINKEDIN_CLIENT_ID'),
      'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
    ],
    'tiktok' => [
      'client_key' => env('TIKTOK_CLIENT_KEY'),
      'client_secret' => env('TIKTOK_CLIENT_SECRET'),
    ],
    'twitter' => [
      'client_id' => env('TWITTER_CLIENT_ID'),
      'client_secret' => env('TWITTER_CLIENT_SECRET'),
    ],
  ],
];
