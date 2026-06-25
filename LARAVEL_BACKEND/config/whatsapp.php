<?php

return [
    'campaign' => [
        'send_delay_ms' => (int) env('WHATSAPP_CAMPAIGN_SEND_DELAY_MS', 1000),
        'limits' => [
            'starter' => ['campaigns_per_month' => 2, 'recipients_per_campaign' => 100],
            'professional' => ['campaigns_per_month' => 10, 'recipients_per_campaign' => 1000],
            'enterprise' => ['campaigns_per_month' => 50, 'recipients_per_campaign' => 10000],
        ],
    ],
];
