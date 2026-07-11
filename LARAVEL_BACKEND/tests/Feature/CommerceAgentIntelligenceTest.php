<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OwnerAnalyticsInvestigation;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\Agent\Intelligence\IntelligenceReasoningService;
use App\Services\AI\AiModelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'agent.company.reasoning_enabled' => false,
            'agent.owner_analytics.use_llm' => false,
        ]);
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $provider->update(['api_key' => 'sk-intel', 'is_enabled' => true]);
        AiModelResolver::clearCache();
    }

    private function intelligenceCompany(): Company
    {
        $company = Company::create([
            'name' => 'Intel Co',
            'email' => 'intel@test.local',
            'status' => 'active',
            'industry' => 'retail',
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
        CompanySetting::create([
            'company_id' => $company->id,
            'agent_commerce_enabled' => true,
            'auto_reply_enabled' => true,
        ]);
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'ph-intel',
            'whatsapp_business_account_id' => 'waba-intel',
            'access_token' => 'tok',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        return $company->fresh(['settings']);
    }

    public function test_intelligence_reason_service_returns_contract(): void
    {
        $company = $this->intelligenceCompany();

        Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-INT-1',
            'customer_phone' => '254700000001',
            'customer_name' => 'Buyer',
            'total' => 5000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $result = app(IntelligenceReasoningService::class)->reason($company, [
            'goal' => 'Should I spend KSh 300,000 on Facebook ads or hire another salesperson?',
            'constraints' => ['Budget cap KSh 300,000'],
            'period' => '30d',
        ]);

        $this->assertSame('Should I spend KSh 300,000 on Facebook ads or hire another salesperson?', $result['goal']);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('assumptions', $result);
        $this->assertArrayHasKey('hypotheses', $result);
        $this->assertArrayHasKey('recommended_actions', $result);
        $this->assertArrayHasKey('executive_decisions', $result);
        $this->assertArrayHasKey('causal_analysis', $result);
        $this->assertArrayHasKey('case_id', $result);
        $this->assertArrayHasKey('probability_scores', $result);
        $this->assertNotNull($result['simulation']);
        $this->assertNotNull($result['plan']);
        $this->assertGreaterThan(0, OwnerAnalyticsInvestigation::where('company_id', $company->id)->count());
    }

    public function test_intelligence_reason_api(): void
    {
        $company = $this->intelligenceCompany();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/company/intelligence/reason', [
            'goal' => 'Why are sales down this month?',
            'period' => '30d',
            'simulate' => true,
            'scenario_type' => 'discount',
            'scenario_inputs' => ['discount_pct' => 15],
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'reasoning' => [
                'goal',
                'confidence',
                'executive_summary',
                'assumptions',
                'evidence',
                'hypotheses',
                'recommended_actions',
                'executive_decisions',
                'simulation',
                'investigation_id',
            ],
        ]);
        $response->assertJsonPath('reasoning.goal', 'Why are sales down this month?');
        $this->assertNotNull($response->json('reasoning.simulation.scenarios'));
    }

    public function test_intelligence_reason_discount_goal_auto_simulates(): void
    {
        $company = $this->intelligenceCompany();

        $result = app(IntelligenceReasoningService::class)->reason($company, [
            'goal' => 'Should we run a 10% discount this week?',
        ]);

        $this->assertNotNull($result['simulation']);
        $this->assertSame('discount', $result['simulation']['scenario_type']);
    }
}
