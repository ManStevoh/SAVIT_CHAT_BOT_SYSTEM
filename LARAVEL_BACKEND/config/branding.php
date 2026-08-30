<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product & legal entity
    |--------------------------------------------------------------------------
    |
    | RelayIQ is the product. Essem Digital Innovation Limited is the company
    | that builds and operates it. essemdigital.com is the company website.
    |
    */

    'product_name' => env('APP_NAME', 'RelayIQ'),

    'legal_entity' => 'Essem Digital Innovation Limited',

    'company_website' => 'https://relayiq.app',

    /*
    | Official public profiles. Instagram uses the handle URL (no share params).
    | Facebook uses the current public page share URL.
    */
    'social' => [
        'instagram' => 'https://www.instagram.com/relayiq.app',
        'facebook' => 'https://www.facebook.com/share/1KxpxJ2VtK/',
    ],

    'product_tagline' => 'Every Conversation. Smarter.',

    'whatsapp_partner' => 'Official WhatsApp Business Partner',

    'powered_by' => 'Powered by Essem Digital',

    'product_of' => 'A product of Essem Digital Innovation Limited',

    'copyright' => '© '.date('Y').' Essem Digital Innovation Limited. All rights reserved.',

];
