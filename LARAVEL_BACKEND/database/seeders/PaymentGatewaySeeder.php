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
                    'currency' => 'kes',
                    'env' => 'sandbox',
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
                    'currency' => 'kes',
                ],
            ],
            [
                'slug' => 'paystack',
                'name' => 'Paystack',
                'is_enabled' => false,
                'config' => [
                    'public_key' => '',
                    'secret_key' => '',
                    'currency' => 'kes',
                    'env' => 'sandbox',
                    'callback_url' => '',
                ],
            ],
            [
                'slug' => 'pesapal',
                'name' => 'Pesapal (Cards, Mobile Money & Bank)',
                'is_enabled' => false,
                'config' => [
                    'consumer_key' => '',
                    'consumer_secret' => '',
                    'env' => 'sandbox',
                    'currency' => 'kes',
                    'ipn_id' => '',
                    'callback_url' => '',
                ],
            ],
            [
                'slug' => 'flutterwave',
                'name' => 'Flutterwave (Cards, Mobile Money, Bank Transfer & USSD)',
                'is_enabled' => false,
                'config' => [
                    'public_key' => '',
                    'secret_key' => '',
                    'secret_hash' => '',
                    'currency' => 'kes',
                    'env' => 'sandbox',
                    'callback_url' => '',
                ],
            ],
            [
                'slug' => 'manual',
                'name' => 'Bank Transfer / Invoice',
                'is_enabled' => false,
                'config' => [
                    'bank_name' => '',
                    'account_name' => '',
                    'account_number' => '',
                    'instructions' => '',
                    'currency' => 'kes',
                    'env' => 'sandbox',
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
