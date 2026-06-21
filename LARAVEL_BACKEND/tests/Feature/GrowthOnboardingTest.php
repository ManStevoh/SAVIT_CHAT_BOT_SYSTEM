<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): User
    {
        $company = Company::create([
            'name' => 'Pilot Biz',
            'email' => 'pilot@test.local',
            'status' => 'active',
            'growth_pilot_at' => now(),
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

    public function test_onboarding_checklist_returns_steps(): void
    {
        Sanctum::actingAs($this->companyUser());

        $response = $this->getJson('/api/company/growth/onboarding');

        $response->assertOk()
            ->assertJsonPath('totalCount', 6)
            ->assertJsonStructure(['steps', 'percentComplete', 'isComplete']);
    }

    public function test_pilot_status_includes_onboarding(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->getJson('/api/company/growth/pilot')
            ->assertOk()
            ->assertJsonPath('isPilot', true)
            ->assertJsonStructure(['onboarding' => ['steps']]);
    }
}
