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

        $free = collect($response->json('plans'))->firstWhere('slug', 'free');
        $growth = collect($response->json('plans'))->firstWhere('slug', 'professional');
        $this->assertNotNull($free);
        $this->assertSame('$0', $free['price']);
        $this->assertSame('$15', $growth['price']);
        $this->assertSame(15.0, (float) $growth['priceAmount']);
        $this->assertSame('USD', $growth['currency']);
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
        $growth = collect($response->json('plans'))->firstWhere('slug', 'professional');
        $custom = collect($response->json('plans'))->firstWhere('slug', 'enterprise');

        $this->assertNotNull($free);
        $this->assertSame(0.0, (float) $free['priceAmount']);
        $this->assertSame('KSh 0', $free['price']);
        $this->assertSame(2000.0, (float) $growth['priceAmount']);
        $this->assertSame('KSh 2,000', $growth['price']);
        $this->assertNull($custom['priceAmount']);
        $this->assertSame('Custom', $custom['price']);
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

        $growth = collect($response->json('plans'))->firstWhere('slug', 'professional');
        $this->assertSame(15.0, (float) $growth['priceAmount']);
    }

    public function test_nigeria_maps_to_ngn(): void
    {
        $response = $this->withHeader('CF-IPCountry', 'NG')
            ->getJson('/api/plans')
            ->assertOk();

        $response->assertJsonPath('currency', 'NGN')
            ->assertJsonPath('source', 'cloudflare');

        $growth = collect($response->json('plans'))->firstWhere('slug', 'professional');
        $this->assertSame(24000.0, (float) $growth['priceAmount']);
        $this->assertStringContainsString('24,000', $growth['price']);
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
        $plan = Plan::where('slug', 'free')->firstOrFail();
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
        $this->assertSame(2000.0, (float) $quote['final_amount']);
        $this->assertSame('KES', $quote['currency']);
    }
}
