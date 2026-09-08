<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Services\Platform\EntitlementService;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'free',
                'price_display' => 'KSh 0',
                'price_amount' => 0,
                'regional_prices' => [
                    'USD' => 0,
                    'KES' => 0,
                    'NGN' => 0,
                ],
                'description' => 'Free forever for businesses — storefront, bookings, and dine-in to get you selling',
                'features' => [
                    'Storefront, bookings & dine-in',
                    '20 physical or digital products',
                    '5 dine-in tables',
                    '30 bookings / month',
                    '1 WhatsApp connection',
                    '50 AI conversations / month',
                    'M-Pesa payments',
                    'RelayIQ branding',
                    'Upgrade anytime to Growth',
                ],
                'entitlements' => EntitlementService::DEFAULTS['free'],
                'popular' => false,
                'is_public' => true,
                'cta' => 'Get started free',
                'sort_order' => 0,
                'stripe_price_id' => null,
                'is_free' => true,
                'has_trial' => false,
                'trial_days' => null,
                'trial_elapsed_action' => null,
            ],
            [
                'name' => 'Starter (legacy)',
                'slug' => 'starter',
                'price_display' => 'KSh 1,499',
                'price_amount' => 1499,
                'regional_prices' => [
                    'USD' => 12,
                    'KES' => 1499,
                    'NGN' => 18000,
                ],
                'description' => 'Hidden grandfathered plan for existing paid Starter subscribers',
                'features' => [
                    '1 WhatsApp connection',
                    '100 products',
                    'AI sales agent',
                    '500 AI conversations/month',
                    'Online storefront & link-in-bio',
                    'M-Pesa, Paystack & Stripe payments',
                    'Bookings & appointments',
                    '1 team member',
                ],
                'entitlements' => EntitlementService::DEFAULTS['starter'],
                'popular' => false,
                'is_public' => false,
                'cta' => 'Start Free Trial',
                'sort_order' => 99,
                'stripe_price_id' => null,
                'is_free' => false,
                'has_trial' => true,
                'trial_days' => 14,
                'trial_elapsed_action' => 'downgrade',
            ],
            [
                'name' => 'Growth',
                'slug' => 'professional',
                'price_display' => 'KSh 2,000',
                'price_amount' => 2000,
                'regional_prices' => [
                    'USD' => 15,
                    'KES' => 2000,
                    'NGN' => 24000,
                ],
                'description' => 'For growing businesses that need more catalog, bookings, and team capacity',
                'features' => [
                    'Everything in Starter',
                    '50 products',
                    '20 dine-in tables',
                    '150 bookings / month',
                    '1,000 AI conversations / month',
                    '3 team members',
                    'M-Pesa, Paystack & Stripe',
                    'WhatsApp campaigns & Growth Engine',
                    'API access & analytics',
                    'No RelayIQ branding',
                    '14-day free trial',
                ],
                'entitlements' => EntitlementService::DEFAULTS['professional'],
                'popular' => true,
                'is_public' => true,
                'cta' => 'Start 14-day trial',
                'sort_order' => 1,
                'stripe_price_id' => null,
                'is_free' => false,
                'has_trial' => true,
                'trial_days' => 14,
                'trial_elapsed_action' => 'downgrade',
            ],
            [
                'name' => 'Custom',
                'slug' => 'enterprise',
                'price_display' => 'Custom',
                'price_amount' => null,
                'regional_prices' => [],
                'description' => 'For high-volume operations — limits, onboarding, and pricing set with the sales team',
                'features' => [
                    'Everything in Growth',
                    'Custom product, table & booking limits',
                    'Custom AI conversation volume',
                    'Custom team seats & WhatsApp numbers',
                    'Priority support & onboarding',
                    'Talk to sales',
                ],
                'entitlements' => EntitlementService::DEFAULTS['enterprise'],
                'popular' => false,
                'is_public' => true,
                'cta' => 'Talk to sales',
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

        $this->seedRegistrationDefault();
    }

    private function seedRegistrationDefault(): void
    {
        $settings = PlatformSetting::query()->first();
        $payload = [
            'default_registration_plan_slug' => 'free',
            'force_default_registration_plan' => true,
        ];

        if ($settings) {
            $settings->update($payload);

            return;
        }

        PlatformSetting::create(array_merge([
            'platform_name' => 'RelayIQ',
            'allow_new_registrations' => true,
        ], $payload));
    }
}
