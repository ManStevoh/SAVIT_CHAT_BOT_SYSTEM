<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Services\PlanLimitService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPlanEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function actingAdmin(): User
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_list_plans_with_entitlements(): void
    {
        $this->actingAdmin();

        $this->getJson('/api/admin/plans')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'starter'])
            ->assertJsonPath('0.entitlements.team', 3);
    }

    public function test_admin_can_update_starter_message_and_api_gates(): void
    {
        $this->actingAdmin();
        $plan = Plan::where('slug', 'starter')->firstOrFail();

        $this->putJson('/api/admin/plans/'.$plan->id, [
            'entitlements' => [
                'messages' => 8000,
                'messagesUnlimited' => false,
                'team' => 5,
                'whatsappNumbers' => 1,
                'apiAccess' => true,
                'analytics' => true,
                'aiPostsPerMonth' => 40,
                'socialPlatforms' => 2,
                'growthEnabled' => true,
                'aiModelModes' => ['auto'],
                'allowByok' => false,
                'allowPhysical' => true,
                'allowDigital' => false,
                'allowService' => true,
                'allowBookings' => true,
                'maxBookingsPerMonth' => 25,
            ],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('plan.entitlements.messages', 8000)
            ->assertJsonPath('plan.entitlements.team', 5)
            ->assertJsonPath('plan.entitlements.apiAccess', true)
            ->assertJsonPath('plan.entitlements.analytics', true)
            ->assertJsonPath('plan.entitlements.aiPostsPerMonth', 40)
            ->assertJsonPath('plan.entitlements.allowDigital', false)
            ->assertJsonPath('plan.entitlements.allowService', true)
            ->assertJsonPath('plan.entitlements.maxBookingsPerMonth', 25);

        $plan->refresh();
        $this->assertSame(8000, $plan->entitlements['messages']);
        $this->assertTrue($plan->entitlements['api_access']);
        $this->assertFalse($plan->entitlements['allow_digital']);
        $this->assertSame(25, $plan->entitlements['max_bookings_per_month']);
        $this->assertSame(8000, PlanLimitService::getMessageLimitForPlan('starter'));
        $this->assertTrue(PlanLimitService::planHasApiAccess('starter'));
    }

    public function test_admin_can_set_unlimited_messages(): void
    {
        $this->actingAdmin();
        $plan = Plan::where('slug', 'professional')->firstOrFail();

        $this->putJson('/api/admin/plans/'.$plan->id, [
            'entitlements' => [
                'messagesUnlimited' => true,
                'team' => 10,
                'apiAccess' => true,
                'analytics' => true,
            ],
        ])->assertOk()
            ->assertJsonPath('plan.entitlements.messagesUnlimited', true)
            ->assertJsonPath('plan.entitlements.messages', null);

        $this->assertNull($plan->fresh()->entitlements['messages']);
    }

    public function test_admin_can_create_custom_plan_with_entitlements(): void
    {
        $this->actingAdmin();

        $this->postJson('/api/admin/plans', [
            'name' => 'Agency',
            'slug' => 'agency',
            'priceDisplay' => 'KES 15,000',
            'priceAmount' => 15000,
            'features' => ['Custom agency limits'],
            'entitlements' => [
                'messages' => 120000,
                'team' => 25,
                'whatsappNumbers' => 1,
                'apiAccess' => true,
                'analytics' => true,
                'aiPostsPerMonth' => 250,
                'socialPlatforms' => 5,
                'aiModelModes' => ['auto', 'platform_default'],
                'allowByok' => true,
                'credentialModes' => ['platform', 'company_preferred'],
            ],
        ])->assertCreated()
            ->assertJsonPath('plan.slug', 'agency')
            ->assertJsonPath('plan.entitlements.messages', 120000)
            ->assertJsonPath('plan.entitlements.team', 25)
            ->assertJsonPath('plan.entitlements.apiAccess', true);

        $this->assertSame(120000, PlanLimitService::getMessageLimitForPlan('agency'));
        $this->assertTrue(PlanLimitService::planHasApiAccess('agency'));
    }
}
