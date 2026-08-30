<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionOffer;
use App\Models\User;
use App\Services\RegionalPricingService;
use App\Support\PlatformDateTime;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PricingAndOffersGapsTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'sk_test_paystack_secret';

    private mixed $previousDefaultCurrency = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->previousDefaultCurrency = config('pricing.default_currency');
        config(['pricing.default_currency' => 'KES']);
        Carbon::setTestNow(Carbon::parse('2026-08-28 12:33:00', 'Africa/Nairobi'));
        PlatformSetting::query()->delete();
        PlatformSetting::create([
            'platform_name' => 'RelayIQ',
            'default_timezone' => 'UTC',
        ]);
        PaymentGateway::query()->where('slug', 'paystack')->delete();
        PaymentGateway::create([
            'slug' => 'paystack',
            'name' => 'Paystack',
            'is_enabled' => true,
            'config' => [
                'public_key' => 'pk_test_x',
                'secret_key' => $this->secret,
                'currency' => 'kes',
            ],
        ]);
        PaymentGateway::clearConfigCache('paystack');
    }

    protected function tearDown(): void
    {
        if ($this->previousDefaultCurrency !== null) {
            config(['pricing.default_currency' => $this->previousDefaultCurrency]);
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{company: Company, owner: User, plan: Plan, admin: User}
     */
    private function actors(): array
    {
        $company = Company::create([
            'name' => 'Gap Co',
            'email' => 'billing@gap-co.test',
            'status' => 'active',
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'status' => 'trial',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(12),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $plan = Plan::where('slug', 'professional')->firstOrFail();

        return compact('company', 'owner', 'plan', 'admin');
    }

    public function test_naive_start_time_is_valid_in_nairobi_not_utc(): void
    {
        ['admin' => $admin] = $this->actors();
        Sanctum::actingAs($admin);

        $this->assertSame('Africa/Nairobi', PlatformDateTime::timezone());

        $offer = $this->postJson('/api/admin/subscription-offers', [
            'name' => '50% Discount',
            'code' => 'SAVE50',
            'discountType' => 'percent',
            'discountValue' => 50,
            'isActive' => true,
            'startsAt' => '2026-08-28T12:32',
            'endsAt' => '2026-09-28T12:32',
        ])->assertCreated()->json('offer');

        $this->assertTrue($offer['isCurrentlyValid']);
        $this->assertStringContainsString('+03:00', $offer['startsAt']);
        $this->assertSame('Africa/Nairobi', $offer['timezone']);

        $stored = SubscriptionOffer::query()->findOrFail($offer['id']);
        $this->assertSame('09:32', $stored->starts_at->utc()->format('H:i'));
        $this->assertTrue($stored->isCurrentlyValid());
    }

    public function test_future_nairobi_window_is_inactive(): void
    {
        ['admin' => $admin] = $this->actors();
        Sanctum::actingAs($admin);

        $offer = $this->postJson('/api/admin/subscription-offers', [
            'name' => 'Later',
            'code' => 'LATER50',
            'discountType' => 'percent',
            'discountValue' => 50,
            'isActive' => true,
            'startsAt' => '2026-08-28T15:00',
            'endsAt' => '2026-09-28T15:00',
        ])->assertCreated()->json('offer');

        $this->assertFalse($offer['isCurrentlyValid']);
    }

    public function test_admin_price_amount_updates_public_kes_price(): void
    {
        ['admin' => $admin] = $this->actors();
        Sanctum::actingAs($admin);
        $plan = Plan::where('slug', 'starter')->firstOrFail();

        $this->putJson('/api/admin/plans/'.$plan->id, [
            'priceDisplay' => '$10',
            'priceAmount' => 10,
        ])->assertOk()
            ->assertJsonPath('plan.priceAmount', 10)
            ->assertJsonPath('plan.regionalPrices.KES', 10);

        $public = $this->getJson('/api/plans?currency=KES')->assertOk();
        $starter = collect($public->json('plans'))->firstWhere('slug', 'starter');
        $this->assertSame(10.0, (float) $starter['priceAmount']);
        $this->assertSame('KSh 10', $starter['price']);

        $usd = collect($this->getJson('/api/plans?currency=USD')->json('plans'))->firstWhere('slug', 'starter');
        $this->assertSame(12.0, (float) $usd['priceAmount']);
    }

    public function test_admin_regional_prices_update_each_currency_independently(): void
    {
        ['admin' => $admin] = $this->actors();
        Sanctum::actingAs($admin);
        $plan = Plan::where('slug', 'professional')->firstOrFail();

        $this->putJson('/api/admin/plans/'.$plan->id, [
            'regionalPrices' => [
                'KES' => 2500,
                'USD' => 19,
                'NGN' => 22000,
            ],
        ])->assertOk()
            ->assertJsonPath('plan.regionalPrices.KES', 2500)
            ->assertJsonPath('plan.regionalPrices.USD', 19)
            ->assertJsonPath('plan.regionalPrices.NGN', 22000);

        $kes = collect($this->getJson('/api/plans?currency=KES')->json('plans'))->firstWhere('slug', 'professional');
        $usd = collect($this->getJson('/api/plans?currency=USD')->json('plans'))->firstWhere('slug', 'professional');
        $ngn = collect($this->getJson('/api/plans?currency=NGN')->json('plans'))->firstWhere('slug', 'professional');

        $this->assertSame(2500.0, (float) $kes['priceAmount']);
        $this->assertSame('KSh 2,500', $kes['price']);
        $this->assertSame(19.0, (float) $usd['priceAmount']);
        $this->assertSame(22000.0, (float) $ngn['priceAmount']);
    }

    public function test_valid_percent_offer_reduces_public_pricing_and_keeps_original(): void
    {
        ['admin' => $admin, 'plan' => $plan] = $this->actors();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/subscription-offers', [
            'name' => '50% Discount',
            'code' => 'SAVE50',
            'discountType' => 'percent',
            'discountValue' => 50,
            'isActive' => true,
        ])->assertCreated();

        $list = (float) app(RegionalPricingService::class)->amountForPlan($plan, 'KES');
        $sale = round($list * 0.5, 2);

        $growth = collect($this->getJson('/api/plans?currency=KES')->json('plans'))
            ->firstWhere('slug', 'professional');

        $this->assertSame($list, (float) $growth['priceAmount']);
        $this->assertSame($sale, (float) $growth['salePriceAmount']);
        $this->assertSame('SAVE50', $growth['offer']['code']);
        $this->assertSame('KSh '.number_format($sale, 0, '.', ','), $growth['price']);
        $this->assertSame('KSh '.number_format($list, 0, '.', ','), $growth['originalPrice']);

        $starter = collect($this->getJson('/api/plans?currency=KES')->json('plans'))
            ->firstWhere('slug', 'starter');
        $this->assertSame('SAVE50', $starter['offer']['code']);
        $this->assertNull(
            collect($this->getJson('/api/plans?currency=KES')->json('plans'))->firstWhere('slug', 'free')['offer']
        );
    }

    public function test_future_offer_does_not_change_public_price(): void
    {
        ['admin' => $admin] = $this->actors();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/subscription-offers', [
            'name' => 'Later',
            'code' => 'LATER50',
            'discountType' => 'percent',
            'discountValue' => 50,
            'isActive' => true,
            'startsAt' => '2026-08-28T15:00',
        ])->assertCreated();

        $growth = collect($this->getJson('/api/plans?currency=KES')->json('plans'))
            ->firstWhere('slug', 'professional');
        $this->assertNull($growth['offer']);
        $this->assertNull($growth['salePriceAmount']);
        $this->assertSame(3999.0, (float) $growth['priceAmount']);
        $this->assertSame('KSh 3,999', $growth['price']);
    }

    public function test_currency_and_plan_locks_are_honored_on_public_pricing(): void
    {
        ['admin' => $admin, 'plan' => $plan] = $this->actors();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/subscription-offers', [
            'name' => 'USD only',
            'code' => 'USD20',
            'discountType' => 'percent',
            'discountValue' => 20,
            'currency' => 'USD',
            'isActive' => true,
        ])->assertCreated();

        $this->postJson('/api/admin/subscription-offers', [
            'name' => 'Growth only',
            'code' => 'GROW10',
            'discountType' => 'percent',
            'discountValue' => 10,
            'planId' => $plan->id,
            'isActive' => true,
        ])->assertCreated();

        $kesGrowth = collect($this->getJson('/api/plans?currency=KES')->json('plans'))
            ->firstWhere('slug', 'professional');
        $kesStarter = collect($this->getJson('/api/plans?currency=KES')->json('plans'))
            ->firstWhere('slug', 'starter');
        $usdGrowth = collect($this->getJson('/api/plans?currency=USD')->json('plans'))
            ->firstWhere('slug', 'professional');

        $this->assertSame('GROW10', $kesGrowth['offer']['code']);
        $this->assertNull($kesStarter['offer']);
        $this->assertSame('USD20', $usdGrowth['offer']['code']);
    }

    public function test_best_public_offer_wins_and_checkout_auto_applies_it(): void
    {
        ['admin' => $admin, 'owner' => $owner, 'plan' => $plan, 'company' => $company] = $this->actors();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/subscription-offers', [
            'name' => 'Small',
            'code' => 'SAVE10',
            'discountType' => 'percent',
            'discountValue' => 10,
            'isActive' => true,
        ])->assertCreated();
        $this->postJson('/api/admin/subscription-offers', [
            'name' => 'Big',
            'code' => 'SAVE50',
            'discountType' => 'percent',
            'discountValue' => 50,
            'isActive' => true,
        ])->assertCreated();

        $growth = collect($this->getJson('/api/plans?currency=KES')->json('plans'))
            ->firstWhere('slug', 'professional');
        $this->assertSame('SAVE50', $growth['offer']['code']);

        $original = (float) app(RegionalPricingService::class)->amountForPlan($plan, 'KES');
        $expected = round($original * 0.5, 2);

        Sanctum::actingAs($owner);
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'access',
                    'reference' => 'essem_sub_'.$company->id.'_auto',
                ],
            ], 200),
        ]);

        $this->postJson('/api/company/paystack/initialize', [
            'planId' => (string) $plan->id,
            'callbackUrl' => 'http://localhost/dashboard/subscription?checkout=success',
        ])->assertOk()
            ->assertJsonPath('amount', $expected)
            ->assertJsonPath('coupon', 'SAVE50')
            ->assertJsonPath('discountAmount', round($original - $expected, 2));
    }

    public function test_explicit_coupon_still_overrides_public_sale(): void
    {
        ['admin' => $admin, 'owner' => $owner, 'plan' => $plan] = $this->actors();
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/subscription-offers', [
            'name' => 'Big',
            'code' => 'SAVE50',
            'discountType' => 'percent',
            'discountValue' => 50,
            'isActive' => true,
        ])->assertCreated();
        $this->postJson('/api/admin/subscription-offers', [
            'name' => 'Small',
            'code' => 'SAVE10',
            'discountType' => 'percent',
            'discountValue' => 10,
            'isActive' => true,
        ])->assertCreated();

        $original = (float) app(RegionalPricingService::class)->amountForPlan($plan, 'KES');
        Sanctum::actingAs($owner);

        $this->postJson('/api/company/coupon/preview', [
            'planId' => (string) $plan->id,
            'couponCode' => 'SAVE10',
            'currency' => 'KES',
        ])->assertOk()
            ->assertJsonPath('code', 'SAVE10')
            ->assertJsonPath('finalAmount', round($original * 0.9, 2));
    }
}
