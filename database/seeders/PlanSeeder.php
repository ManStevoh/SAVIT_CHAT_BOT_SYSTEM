<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price_display' => '$29',
                'price_amount' => 29,
                'description' => 'Perfect for small businesses just getting started',
                'features' => [
                    '1 WhatsApp number',
                    '1,000 messages/month',
                    'Basic AI chatbot',
                    'Order management',
                    'Email support',
                ],
                'popular' => false,
                'cta' => 'Start Free Trial',
                'sort_order' => 0,
            ],
            [
                'name' => 'Growth',
                'slug' => 'professional',
                'price_display' => '$99',
                'price_amount' => 99,
                'description' => 'For growing businesses with higher volume',
                'features' => [
                    '3 WhatsApp numbers',
                    '10,000 messages/month',
                    'Advanced AI with GPT-4',
                    'Multi-agent inbox',
                    'Analytics dashboard',
                    'Priority support',
                    'API access',
                ],
                'popular' => true,
                'cta' => 'Start Free Trial',
                'sort_order' => 1,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price_display' => 'Custom',
                'price_amount' => null,
                'description' => 'For large organizations with custom needs',
                'features' => [
                    'Unlimited WhatsApp numbers',
                    'Unlimited messages',
                    'Custom AI training',
                    'Dedicated account manager',
                    'Custom integrations',
                    'SLA guarantee',
                    'On-premise option',
                ],
                'popular' => false,
                'cta' => 'Contact Sales',
                'sort_order' => 2,
            ],
        ];

        foreach ($plans as $data) {
            Plan::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
