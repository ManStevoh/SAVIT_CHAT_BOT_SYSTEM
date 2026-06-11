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
                    '5,000 messages/month',
                    'Basic AI chatbot',
                    'Order management',
                    'Growth Engine: 20 AI posts/mo, 1 social platform',
                    'Attribution tracking (WhatsApp ref links)',
                    'Email support',
                ],
                'popular' => false,
                'cta' => 'Start Free Trial',
                'sort_order' => 0,
                'stripe_price_id' => null,
                'is_free' => false,
                'has_trial' => true,
                'trial_days' => 14,
                'trial_elapsed_action' => 'downgrade',
            ],
            [
                'name' => 'Growth',
                'slug' => 'professional',
                'price_display' => '$99',
                'price_amount' => 99,
                'description' => 'For growing businesses with higher volume',
                'features' => [
                    '3 WhatsApp numbers',
                    '50,000 messages/month',
                    'Advanced AI with GPT-4',
                    'Multi-agent inbox',
                    'Growth Engine: 100 AI posts/mo, 3 social platforms',
                    'Meta metrics + ad spend sync, CRM follow-up agent',
                    'Analytics dashboard',
                    'Priority support',
                    'API access',
                ],
                'popular' => true,
                'cta' => 'Start Free Trial',
                'sort_order' => 1,
                'stripe_price_id' => null,
                'is_free' => false,
                'has_trial' => true,
                'trial_days' => 14,
                'trial_elapsed_action' => 'downgrade',
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
                    'Growth Engine: 500 AI posts/mo, 10 social platforms',
                    'Portfolio AI + cross-brand insights',
                    'Custom AI training',
                    'Dedicated account manager',
                    'Custom integrations (GA4, email, CRM)',
                    'SLA guarantee',
                    'On-premise option',
                ],
                'popular' => false,
                'cta' => 'Contact Sales',
                'sort_order' => 2,
                'stripe_price_id' => null,
                'is_free' => false,
                'has_trial' => false,
                'trial_days' => null,
                'trial_elapsed_action' => null,
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
