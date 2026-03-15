<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            [
                'slug' => 'stripe',
                'name' => 'Stripe',
                'is_enabled' => false,
                'config' => [
                    'key' => '',
                    'secret' => '',
                    'webhook_secret' => '',
                    'trial_days' => 14,
                    'currency' => 'usd',
                ],
            ],
            [
                'slug' => 'mpesa',
                'name' => 'Lipa Na M-Pesa',
                'is_enabled' => false,
                'config' => [
                    'consumer_key' => '',
                    'consumer_secret' => '',
                    'shortcode' => '',
                    'passkey' => '',
                    'env' => 'sandbox',
                    'callback_url' => '',
                ],
            ],
        ];

        foreach ($gateways as $data) {
            PaymentGateway::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
