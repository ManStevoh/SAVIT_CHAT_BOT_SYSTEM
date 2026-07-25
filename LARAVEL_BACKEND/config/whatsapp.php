<?php

return [
    /*
    | When false (default), WhatsApp AI auto-replies run after the HTTP response
    | in the same PHP process — no queue:work required for bot replies.
    | Set true to push ProcessIncomingWhatsAppMessage onto the queue worker instead.
    */
    'auto_reply_via_queue' => (bool) env('WHATSAPP_AUTO_REPLY_VIA_QUEUE', false),

    'campaign' => [
        'send_delay_ms' => (int) env('WHATSAPP_CAMPAIGN_SEND_DELAY_MS', 1000),
        'limits' => [
            'starter' => ['campaigns_per_month' => 2, 'recipients_per_campaign' => 100],
            'professional' => ['campaigns_per_month' => 10, 'recipients_per_campaign' => 1000],
            'enterprise' => ['campaigns_per_month' => 50, 'recipients_per_campaign' => 10000],
        ],
    ],
];
