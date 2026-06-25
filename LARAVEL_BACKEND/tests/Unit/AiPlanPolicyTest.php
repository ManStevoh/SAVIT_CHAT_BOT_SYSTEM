<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Subscription;
use App\Services\PlanLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function companyOnPlan(string $plan): Company
    {
        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local', 'status' => 'active']);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan,
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'ai_model_mode' => 'specific',
            'ai_credential_mode' => 'company',
        ]);

        return $company->fresh(['settings']);
    }

    public function test_starter_clamps_to_auto_model_mode(): void
    {
        $company = $this->companyOnPlan('starter');

        $this->assertSame('auto', PlanLimitService::effectiveAiModelMode($company));
        $this->assertFalse(PlanLimitService::planAllowsByok('starter'));
        $this->assertSame('platform', PlanLimitService::effectiveCredentialMode($company));
    }

    public function test_professional_allows_platform_default_and_byok_preferred(): void
    {
        $company = $this->companyOnPlan('professional');
        $company->settings->update(['ai_model_mode' => 'platform_default', 'ai_credential_mode' => 'company_preferred']);

        $this->assertSame('platform_default', PlanLimitService::effectiveAiModelMode($company->fresh(['settings'])));
        $this->assertTrue(PlanLimitService::planAllowsByok('professional'));
        $this->assertSame('company_preferred', PlanLimitService::effectiveCredentialMode($company->fresh(['settings'])));
        $this->assertFalse(PlanLimitService::isCredentialModeAllowed('professional', 'company'));
    }

    public function test_enterprise_allows_specific_model_and_company_only_keys(): void
    {
        $company = $this->companyOnPlan('enterprise');

        $this->assertSame('specific', PlanLimitService::effectiveAiModelMode($company));
        $this->assertTrue(PlanLimitService::isAiModelModeAllowed('enterprise', 'specific'));
        $this->assertTrue(PlanLimitService::isCredentialModeAllowed('enterprise', 'company'));
        $this->assertSame('company', PlanLimitService::effectiveCredentialMode($company));
    }
}
