<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAssignSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_active_plan_sets_plan_price_not_trial_amount(): void
    {
        Plan::query()->updateOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'price_display' => '$29',
                'price_amount' => 29,
                'description' => 'Starter',
                'features' => [],
                'popular' => false,
                'cta' => 'Start',
                'sort_order' => 0,
                'is_free' => false,
            ]
        );

        $company = Company::create([
            'name' => 'big company',
            'email' => 'big@test.local',
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'status' => 'trial',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(13),
            'amount' => 0,
            'billing_cycle' => 'monthly',
            'stripe_subscription_id' => 'sub_old',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/subscriptions/{$subscription->id}", [
            'plan' => 'starter',
            'status' => 'active',
            'billingCycle' => 'monthly',
        ])->assertOk()
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.amount', 29)
            ->assertJsonPath('subscription.billingCycle', 'monthly');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
            'amount' => 29,
            'stripe_subscription_id' => null,
        ]);
    }
}
