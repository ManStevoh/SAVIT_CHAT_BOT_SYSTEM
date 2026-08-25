<?php

return [
    /*
    | Default: run AI auto-reply synchronously in the webhook/request (reliable, no queue worker).
    | Set WHATSAPP_AUTO_REPLY_VIA_QUEUE=true to use a queue worker instead.
    | Set WHATSAPP_AUTO_REPLY_AFTER_RESPONSE=true for faster Meta ACKs (less reliable on some hosts).
    */
    'auto_reply_via_queue' => (bool) env('WHATSAPP_AUTO_REPLY_VIA_QUEUE', false),
    'auto_reply_after_response' => (bool) env('WHATSAPP_AUTO_REPLY_AFTER_RESPONSE', false),

    'campaign' => [
        'send_delay_ms' => (int) env('WHATSAPP_CAMPAIGN_SEND_DELAY_MS', 1000),
        'limits' => [
            'starter' => ['campaigns_per_month' => 2, 'recipients_per_campaign' => 100],
            'professional' => ['campaigns_per_month' => 10, 'recipients_per_campaign' => 1000],
            'enterprise' => ['campaigns_per_month' => 50, 'recipients_per_campaign' => 10000],
        ],
    ],
];
