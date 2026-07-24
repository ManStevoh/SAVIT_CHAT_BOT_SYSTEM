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
                'name' => 'Starter',
                'slug' => 'starter',
                'price_display' => '$29',
                'price_amount' => 29,
                'description' => 'Perfect for small businesses just getting started',
                'features' => [
                    '1 WhatsApp Business number',
                    '5,000 messages/month (auto-replies gated)',
                    'Conversational AI OS (sales, support, catalog & orders)',
                    'AI auto-replies with memory & learning',
                    'Order management',
                    'Customer payments (M-Pesa / Paystack / Stripe when enabled)',
                    'Growth Engine: 20 AI posts/mo, 1 social platform',
                    'Attribution tracking (WhatsApp ref links)',
                    'Up to 3 team seats',
                    'Email support',
                ],
                'entitlements' => EntitlementService::DEFAULTS['starter'],
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
                    'Everything in Starter',
                    'Bookings & services catalog',
                    '50,000 messages/month',
                    'Advanced AI model options + BYOK preferred',
                    'Multi-agent inbox (up to 10 seats)',
                    'Growth Engine: 100 AI posts/mo, 3 social platforms',
                    'Analytics dashboard',
                    'API access',
                    'Priority support',
                ],
                'entitlements' => EntitlementService::DEFAULTS['professional'],
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
                    'Everything in Growth',
                    'Unlimited messages',
                    'Up to 50 team seats',
                    'Growth Engine: 500 AI posts/mo, 10 social platforms',
                    'Custom AI model selection + company API keys',
                    'Enterprise onboarding & SLAs (contact sales)',
                    'Custom integrations (contact sales)',
                ],
                'entitlements' => EntitlementService::DEFAULTS['enterprise'],
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
