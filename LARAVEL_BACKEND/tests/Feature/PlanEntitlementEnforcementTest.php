<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanyEntitlementOverride;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Growth\GrowthLimitService;
use App\Services\PlanLimitService;
use App\Services\Platform\EntitlementService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanEntitlementEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    /**
     * @return array{company: Company, owner: User}
     */
    private function companyOnPlan(string $plan, int $extraSeats = 0): array
    {
        $company = Company::create([
            'name' => 'Limit Co '.$plan,
            'email' => $plan.uniqid().'@limits.test',
            'status' => 'active',
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        CompanySetting::create(['company_id' => $company->id]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        for ($i = 0; $i < $extraSeats; $i++) {
            User::factory()->create([
                'company_id' => $company->id,
                'role' => 'agent',
                'email_verified_at' => now(),
            ]);
        }

        return ['company' => $company->fresh(), 'owner' => $owner];
    }

    public function test_seeded_plan_features_match_enforced_entitlements(): void
    {
        $starter = Plan::where('slug', 'starter')->firstOrFail();
        $growth = Plan::where('slug', 'professional')->firstOrFail();
        $enterprise = Plan::where('slug', 'enterprise')->firstOrFail();

        $this->assertSame(5000, $starter->entitlements['messages']);
        $this->assertSame(3, $starter->entitlements['team']);
        $this->assertFalse($starter->entitlements['api_access']);
        $this->assertFalse($starter->entitlements['analytics']);
        $this->assertSame(20, $starter->entitlements['ai_posts_per_month']);
        $this->assertSame(1, $starter->entitlements['whatsapp_numbers']);
        $this->assertTrue($starter->entitlements['allow_physical']);
        $this->assertTrue($starter->entitlements['allow_digital']);
        $this->assertFalse($starter->entitlements['allow_service']);
        $this->assertFalse($starter->entitlements['allow_bookings']);
        $this->assertSame(0, $starter->entitlements['max_bookings_per_month']);

        $this->assertSame(50000, $growth->entitlements['messages']);
        $this->assertTrue($growth->entitlements['api_access']);
        $this->assertTrue($growth->entitlements['analytics']);
        $this->assertSame(100, $growth->entitlements['ai_posts_per_month']);
        $this->assertSame(3, $growth->entitlements['social_platforms']);
        $this->assertTrue($growth->entitlements['allow_service']);
        $this->assertTrue($growth->entitlements['allow_bookings']);
        $this->assertSame(50, $growth->entitlements['max_bookings_per_month']);

        $this->assertNull($enterprise->entitlements['messages']);
        $this->assertSame(50, $enterprise->entitlements['team']);
        $this->assertSame(500, $enterprise->entitlements['ai_posts_per_month']);
        $this->assertNull($enterprise->entitlements['max_bookings_per_month']);

        $response = $this->getJson('/api/plans')->assertOk();
        $plans = collect($response->json());
        $this->assertFalse((bool) data_get($plans->firstWhere('slug', 'starter'), 'entitlements.apiAccess'));
        $this->assertTrue((bool) data_get($plans->firstWhere('slug', 'professional'), 'entitlements.apiAccess'));
        $this->assertFalse((bool) data_get($plans->firstWhere('slug', 'starter'), 'entitlements.allowService'));
        $this->assertSame(50, data_get($plans->firstWhere('slug', 'professional'), 'entitlements.maxBookingsPerMonth'));
        $this->assertNull(data_get($plans->firstWhere('slug', 'enterprise'), 'entitlements.maxBookingsPerMonth'));
    }

    public function test_catalog_product_types_are_gated_by_plan(): void
    {
        ['company' => $starter, 'owner' => $starterOwner] = $this->companyOnPlan('starter');
        Sanctum::actingAs($starterOwner);

        $this->postJson('/api/company/products', [
            'name' => 'Starter service',
            'price' => 100,
            'stock' => 0,
            'productType' => 'service',
        ])->assertStatus(403)
            ->assertJsonPath('code', 'catalog_type_required');

        $starterProduct = $this->postJson('/api/company/products', [
            'name' => 'Starter download',
            'price' => 100,
            'stock' => 0,
            'productType' => 'digital',
        ])->assertOk();

        $this->putJson('/api/company/products/'.$starterProduct->json('product.id'), [
            'productType' => 'service',
        ])->assertStatus(403)
            ->assertJsonPath('code', 'catalog_type_required');

        ['owner' => $professionalOwner] = $this->companyOnPlan('professional');
        Sanctum::actingAs($professionalOwner);

        $this->postJson('/api/company/products', [
            'name' => 'Professional service',
            'price' => 100,
            'stock' => 0,
            'productType' => 'service',
        ])->assertOk();

        $this->assertFalse(PlanLimitService::companyAllowsProductType($starter, 'service'));
        $this->assertTrue(PlanLimitService::companyAllowsBookings($starter) === false);
        $this->assertSame(0, PlanLimitService::getMaxBookingsPerMonth($starter));
    }

    public function test_message_limit_enforced_and_enterprise_unlimited(): void
    {
        ['company' => $starter] = $this->companyOnPlan('starter');
        CompanyEntitlementOverride::create([
            'company_id' => $starter->id,
            'overrides' => ['messages' => 2],
        ]);

        $chat = Chat::create([
            'company_id' => $starter->id,
            'customer_name' => 'Limit Tester',
            'customer_phone' => '254700000001',
            'status' => 'open',
        ]);
        Message::create(['chat_id' => $chat->id, 'sender' => 'customer', 'content' => 'm1', 'status' => 'received']);
        Message::create(['chat_id' => $chat->id, 'sender' => 'customer', 'content' => 'm2', 'status' => 'received']);

        $this->assertFalse(PlanLimitService::isWithinMessageLimit($starter->fresh()));

        ['company' => $enterprise] = $this->companyOnPlan('enterprise');
        $this->assertTrue(PlanLimitService::hasUnlimitedMessages($enterprise));
        $this->assertTrue(PlanLimitService::isWithinMessageLimit($enterprise));
        $this->assertNull(app(EntitlementService::class)->messageLimit($enterprise));
        $this->assertNull(PlanLimitService::getMaxBookingsPerMonth($enterprise));
    }

    public function test_team_seat_limit_blocks_invite_on_starter(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->companyOnPlan('starter', 2);
        Sanctum::actingAs($owner);

        $this->assertFalse(PlanLimitService::canAddTeamMember($company));

        $this->postJson('/api/company/team', [
            'name' => 'Extra Agent',
            'email' => 'extra-agent@limits.test',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'team_limit_reached');
    }

    public function test_growth_can_invite_within_team_limit(): void
    {
        ['owner' => $owner] = $this->companyOnPlan('professional');
        Sanctum::actingAs($owner);

        $this->postJson('/api/company/team', [
            'name' => 'Growth Agent',
            'email' => 'growth-agent@limits.test',
            'role' => 'agent',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('member.email', 'growth-agent@limits.test');
    }

    public function test_api_access_gated_by_plan(): void
    {
        ['owner' => $starterOwner] = $this->companyOnPlan('starter');
        Sanctum::actingAs($starterOwner);
        $this->postJson('/api/company/api-platform/keys', ['name' => 'Starter key'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'api_access_required');

        ['owner' => $growthOwner] = $this->companyOnPlan('professional');
        Sanctum::actingAs($growthOwner);
        $this->postJson('/api/company/api-platform/keys', ['name' => 'Growth key'])
            ->assertCreated();
    }

    public function test_analytics_gated_by_plan(): void
    {
        ['owner' => $starterOwner] = $this->companyOnPlan('starter');
        Sanctum::actingAs($starterOwner);
        $this->getJson('/api/company/analytics')
            ->assertStatus(403)
            ->assertJsonPath('code', 'analytics_required');

        ['owner' => $growthOwner] = $this->companyOnPlan('professional');
        Sanctum::actingAs($growthOwner);
        $this->getJson('/api/company/analytics')->assertOk();
    }

    public function test_growth_limits_come_from_entitlements(): void
    {
        ['company' => $starter] = $this->companyOnPlan('starter');
        ['company' => $growth] = $this->companyOnPlan('professional');
        ['company' => $enterprise] = $this->companyOnPlan('enterprise');

        $this->assertSame(20, GrowthLimitService::getAiPostsLimit($starter));
        $this->assertSame(1, GrowthLimitService::getPlatformLimit($starter));

        $this->assertSame(100, GrowthLimitService::getAiPostsLimit($growth));
        $this->assertSame(3, GrowthLimitService::getPlatformLimit($growth));

        $this->assertSame(500, GrowthLimitService::getAiPostsLimit($enterprise));
        $this->assertSame(10, GrowthLimitService::getPlatformLimit($enterprise));
    }

    public function test_pricing_copy_no_longer_claims_unenforced_whatsapp_multi_or_gpt4(): void
    {
        $growth = Plan::where('slug', 'professional')->firstOrFail();
        $blob = strtolower(implode(' ', $growth->features));

        $this->assertStringNotContainsString('gpt-4', $blob);
        $this->assertStringNotContainsString('3 whatsapp', $blob);
        $this->assertStringContainsString('api access', $blob);
        $this->assertStringContainsString('analytics', $blob);
    }
}
