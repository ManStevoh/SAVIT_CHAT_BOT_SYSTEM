<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GrowthAdSpendEntry;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthAdSpendTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): User
    {
        $company = Company::create([
            'name' => 'Ad Co',
            'email' => 'adco@test.local',
            'status' => 'active',
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
    }

    public function test_store_ad_spend_and_analytics_reflects_roi(): void
    {
        Sanctum::actingAs($this->companyUser());
        $user = auth()->user();

        $this->postJson('/api/company/growth/ad-spend', [
            'platform' => 'facebook',
            'amount' => 10000,
            'spentAt' => now()->toDateString(),
        ])->assertOk()->assertJsonPath('success', true);

        $analytics = $this->getJson('/api/company/growth/analytics?period=30d');
        $analytics->assertOk()
            ->assertJsonPath('summary.adSpend', 10000);

        $this->assertSame(1, GrowthAdSpendEntry::where('company_id', $user->company_id)->count());
    }
}
