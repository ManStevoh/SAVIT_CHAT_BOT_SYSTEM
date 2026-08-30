<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Services\RegionalPricingService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionalPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_public_plans_default_to_usd_without_geo(): void
    {
        config(['pricing.default_currency' => 'USD']);

        $response = $this->getJson('/api/plans')->assertOk();

        $response->assertJsonPath('currency', 'USD')
            ->assertJsonPath('source', 'default')
            ->assertJsonPath('availableCurrencies.0.code', 'KES')
            ->assertJsonPath('availableCurrencies.1.code', 'USD')
            ->assertJsonPath('availableCurrencies.2.code', 'NGN')
            ->assertJsonStructure([
                'currency',
                'availableCurrencies',
                'plans' => [
                    ['id', 'slug', 'price', 'priceAmount', 'currency', 'hasTrial'],
                ],
            ]);

        $starter = collect($response->json('plans'))->firstWhere('slug', 'starter');
        $this->assertSame('$12', $starter['price']);
        $this->assertSame(12.0, (float) $starter['priceAmount']);
        $this->assertSame('USD', $starter['currency']);
    }

    public function test_cloudflare_country_header_switches_to_kes(): void
    {
        $response = $this->withHeader('CF-IPCountry', 'KE')
            ->getJson('/api/plans')
            ->assertOk();

        $response->assertJsonPath('currency', 'KES')
            ->assertJsonPath('detectedCountry', 'KE')
            ->assertJsonPath('source', 'cloudflare');

        $free = collect($response->json('plans'))->firstWhere('slug', 'free');
        $starter = collect($response->json('plans'))->firstWhere('slug', 'starter');
        $growth = collect($response->json('plans'))->firstWhere('slug', 'professional');
        $business = collect($response->json('plans'))->firstWhere('slug', 'enterprise');

        $this->assertSame(0.0, (float) $free['priceAmount']);
        $this->assertSame('KSh 0', $free['price']);
        $this->assertSame(1499.0, (float) $starter['priceAmount']);
        $this->assertSame('KSh 1,499', $starter['price']);
        $this->assertSame(3999.0, (float) $growth['priceAmount']);
        $this->assertSame('KSh 3,999', $growth['price']);
        $this->assertSame(9999.0, (float) $business['priceAmount']);
        $this->assertSame('KSh 9,999', $business['price']);
    }

    public function test_query_currency_overrides_geo_and_sets_cookie(): void
    {
        $response = $this->withHeader('CF-IPCountry', 'KE')
            ->getJson('/api/plans?currency=USD')
            ->assertOk();

        $response->assertJsonPath('currency', 'USD')
            ->assertJsonPath('source', 'query')
            ->assertJsonPath('detectedCountry', 'KE');

        $cookieName = (string) config('pricing.cookie', 'pricing_currency');
        $response->assertPlainCookie($cookieName, 'USD');

        $starter = collect($response->json('plans'))->firstWhere('slug', 'starter');
        $this->assertSame(12.0, (float) $starter['priceAmount']);
    }

    public function test_nigeria_maps_to_ngn(): void
    {
        $response = $this->withHeader('CF-IPCountry', 'NG')
            ->getJson('/api/plans')
            ->assertOk();

        $response->assertJsonPath('currency', 'NGN')
            ->assertJsonPath('source', 'cloudflare');

        $starter = collect($response->json('plans'))->firstWhere('slug', 'starter');
        $this->assertSame(18000.0, (float) $starter['priceAmount']);
        $this->assertStringContainsString('18,000', $starter['price']);
    }

    public function test_force_country_env_works_for_local_dev(): void
    {
        config(['pricing.force_country' => 'KE']);

        $response = $this->getJson('/api/plans')->assertOk();

        $response->assertJsonPath('currency', 'KES')
            ->assertJsonPath('source', 'forced')
            ->assertJsonPath('detectedCountry', 'KE');
    }

    public function test_plan_regional_prices_override_config(): void
    {
        $plan = Plan::where('slug', 'starter')->firstOrFail();
        $plan->update(['regional_prices' => ['KES' => 2500, 'USD' => 29]]);

        $amount = app(RegionalPricingService::class)->amountForPlan($plan->fresh(), 'KES');
        $this->assertSame(2500.0, $amount);
    }

    public function test_subscription_quote_uses_regional_kes_amount(): void
    {
        $plan = Plan::where('slug', 'professional')->firstOrFail();
        $company = \App\Models\Company::create([
            'name' => 'Geo Co',
            'email' => 'geo@test.local',
            'status' => 'active',
        ]);

        $quote = app(\App\Services\SubscriptionPricingService::class)
            ->quote($plan, $company, null, 'KES');

        $this->assertTrue($quote['success']);
        $this->assertSame(3999.0, (float) $quote['final_amount']);
        $this->assertSame('KES', $quote['currency']);
    }
}
