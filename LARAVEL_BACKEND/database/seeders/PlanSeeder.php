<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Services\Platform\EntitlementService;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price_display' => 'KSh 0',
                'price_amount' => 0,
                'regional_prices' => [
                    'USD' => 0,
                    'KES' => 0,
                    'NGN' => 0,
                ],
                'description' => 'Get started selling on WhatsApp with essential commerce tools',
                'features' => [
                    '1 WhatsApp connection',
                    '20 products',
                    '50 AI conversations/month',
                    'Basic storefront & link-in-bio',
                    'Basic customer inbox',
                    'M-Pesa payment integration',
                    'RelayIQ branding',
                    'Limited automation (5 AI posts/mo)',
                ],
                'entitlements' => EntitlementService::DEFAULTS['free'],
                'popular' => false,
                'cta' => 'Get Started Free',
                'sort_order' => 0,
                'stripe_price_id' => null,
                'is_free' => true,
                'has_trial' => false,
                'trial_days' => null,
                'trial_elapsed_action' => null,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price_display' => 'KSh 1,499',
                'price_amount' => 1499,
                'regional_prices' => [
                    'USD' => 12,
                    'KES' => 1499,
                    'NGN' => 18000,
                ],
                'description' => 'Essential AI sales agent and commerce automation for solo sellers and small shops',
                'features' => [
                    '1 WhatsApp connection',
                    '100 products',
                    'AI sales agent',
                    '500 AI conversations/month',
                    'Online storefront & link-in-bio',
                    'M-Pesa, Paystack & Stripe payments',
                    'Bookings & appointments',
                    'Automated replies',
                    'Basic customer CRM & analytics',
                    '1 team member',
                    'Basic AI automations (20 posts/mo)',
                ],
                'entitlements' => EntitlementService::DEFAULTS['starter'],
                'popular' => false,
                'cta' => 'Start Free Trial',
                'sort_order' => 1,
                'stripe_price_id' => null,
                'is_free' => false,
                'has_trial' => true,
                'trial_days' => 14,
                'trial_elapsed_action' => 'downgrade',
            ],
            [
                'name' => 'Growth',
                'slug' => 'professional',
                'price_display' => 'KSh 3,999',
                'price_amount' => 3999,
                'regional_prices' => [
                    'USD' => 29,
                    'KES' => 3999,
                    'NGN' => 45000,
                ],
                'description' => 'For growing businesses needing higher volume, multi-number WhatsApp, and advanced CRM',
                'features' => [
                    '2 WhatsApp connections',
                    '1,000 products',
                    'AI sales agent',
                    '2,000 AI conversations/month',
                    'Online storefront + Dine-in QR ordering',
                    'M-Pesa, Paystack & Stripe payments',
                    'Bookings & services',
                    'Automated replies & campaigns',
                    'Advanced customer CRM',
                    'Standard analytics dashboard',
                    '5 team members',
                    'Advanced AI automations (100 posts/mo, 3 platforms)',
                    'API access',
                ],
                'entitlements' => EntitlementService::DEFAULTS['growth'],
                'popular' => true,
                'cta' => 'Start Free Trial',
                'sort_order' => 2,
                'stripe_price_id' => null,
                'is_free' => false,
                'has_trial' => true,
                'trial_days' => 14,
                'trial_elapsed_action' => 'downgrade',
            ],
            [
                'name' => 'Business',
                'slug' => 'enterprise',
                'price_display' => 'KSh 9,999',
                'price_amount' => 9999,
                'regional_prices' => [
                    'USD' => 79,
                    'KES' => 9999,
                    'NGN' => 120000,
                ],
                'description' => 'Maximum power and capacity for established brands and high-volume teams',
                'features' => [
                    '5 WhatsApp connections',
                    'Unlimited products',
                    'AI sales agent (custom models & BYOK)',
                    '10,000 AI conversations/month',
                    'Online storefront + Dine-in QR ordering',
                    'M-Pesa, Paystack & Stripe payments',
                    'Unlimited bookings & services',
                    'Automated replies & high-volume campaigns',
                    'Advanced customer CRM',
                    'Advanced analytics & custom reports',
                    '15 team members',
                    'Unlimited AI automations (500 posts/mo, 10 platforms)',
                    'Priority support & API access',
                ],
                'entitlements' => EntitlementService::DEFAULTS['business'],
                'popular' => false,
                'cta' => 'Start Free Trial',
                'sort_order' => 3,
                'stripe_price_id' => null,
                'is_free' => false,
                'has_trial' => true,
                'trial_days' => 14,
                'trial_elapsed_action' => 'downgrade',
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
