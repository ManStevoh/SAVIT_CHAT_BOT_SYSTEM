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
                'regional_prices' => [
                    'USD' => 29,
                    'KES' => 3799,
                    'NGN' => 45000,
                ],
                'description' => 'WhatsApp AI commerce with physical & digital catalog and a web storefront',
                'features' => [
                    '1 WhatsApp Business number',
                    '5,000 messages/month (auto-replies gated)',
                    'AI commerce agent (sales, support, catalog & orders)',
                    'Physical & digital products + order management',
                    'Web storefront + link-in-bio',
                    'Customer payments (M-Pesa / Paystack / Stripe when enabled)',
                    'Delivery zones & tax settings',
                    'Growth Engine: 20 AI posts/mo, 1 social platform',
                    'WhatsApp campaigns: 2/mo (up to 100 recipients)',
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
                'regional_prices' => [
                    'USD' => 99,
                    'KES' => 12999,
                    'NGN' => 155000,
                ],
                'description' => 'For growing businesses — bookings, dine-in, higher volume, and advanced AI',
                'features' => [
                    'Everything in Starter',
                    'Bookings & services catalog (50 bookings/mo)',
                    'Dine-in table QR ordering',
                    '50,000 messages/month',
                    'Advanced AI model options + BYOK preferred',
                    'Multi-agent inbox (up to 10 seats)',
                    'Growth Engine: 100 AI posts/mo, 3 social platforms',
                    'WhatsApp campaigns: 10/mo (up to 1,000 recipients)',
                    'Analytics dashboard + API access',
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
                'regional_prices' => [
                    'USD' => null,
                    'KES' => null,
                    'NGN' => null,
                ],
                'description' => 'For large organizations with custom limits, models, and SLAs',
                'features' => [
                    'Everything in Growth',
                    'Unlimited messages & bookings',
                    'Up to 50 team seats',
                    'Growth Engine: 500 AI posts/mo, 10 social platforms',
                    'WhatsApp campaigns: 50/mo (up to 10,000 recipients)',
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
