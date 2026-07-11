<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\AgentTrustLog;
use App\Models\BusinessOpportunity;
use App\Models\BusinessWorldSnapshot;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\Agent\Platform\BusinessHealthScoreService;
use App\Services\Agent\Platform\BusinessWorldModelService;
use App\Services\Agent\Platform\OpportunityDetectionService;
use App\Services\Agent\Platform\OrganizationalMemoryService;
use App\Services\Agent\Platform\SkillModuleRegistry;
use App\Services\AI\AiModelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.company.reasoning_enabled' => false]);
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $provider->update(['api_key' => 'sk-platform', 'is_enabled' => true]);
        AiModelResolver::clearCache();
    }

    private function platformCompany(): Company
    {
        $company = Company::create([
            'name' => 'Platform Co',
            'email' => 'platform@test.local',
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
            'phone_number_id' => 'ph-plat',
            'whatsapp_business_account_id' => 'waba-plat',
            'access_token' => 'tok',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        return $company->fresh(['settings']);
    }

    public function test_world_model_snapshot_persists(): void
    {
        $company = $this->platformCompany();
        $snapshot = app(BusinessWorldModelService::class)->snapshot($company, 'test');

        $this->assertInstanceOf(BusinessWorldSnapshot::class, $snapshot);
        $this->assertArrayHasKey('orders', $snapshot->world_model);
        $this->assertDatabaseHas('business_world_snapshots', ['company_id' => $company->id]);
    }

    public function test_opportunity_detection_finds_bundle_pattern(): void
    {
        $company = $this->platformCompany();

        for ($i = 0; $i < 3; $i++) {
            $order = Order::create([
                'company_id' => $company->id,
                'order_number' => 'BND-'.$i,
                'customer_phone' => '25470000000'.$i,
                'customer_name' => 'Customer '.$i,
                'total' => 1000,
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ]);
            OrderProduct::create(['order_id' => $order->id, 'name' => 'Laptop', 'quantity' => 1, 'price' => 600]);
            OrderProduct::create(['order_id' => $order->id, 'name' => 'Mouse', 'quantity' => 1, 'price' => 400]);
        }

        $opps = app(OpportunityDetectionService::class)->detectForCompany($company);

        $this->assertNotEmpty($opps);
        $this->assertDatabaseHas('business_opportunities', [
            'company_id' => $company->id,
            'opportunity_type' => 'bundle',
        ]);
    }

    public function test_health_score_computed(): void
    {
        $company = $this->platformCompany();
        $score = app(BusinessHealthScoreService::class)->computeForCompany($company);

        $this->assertGreaterThan(0, $score->overall_score);
        $this->assertDatabaseHas('business_health_scores', ['company_id' => $company->id]);
    }

    public function test_organizational_memory_stored(): void
    {
        $company = $this->platformCompany();
        app(OrganizationalMemoryService::class)->store(
            $company->id,
            'pricing',
            'Volume discount policy',
            'Schools above 20 units get 8% off — approved March 2026',
        );

        $this->assertDatabaseHas('organizational_memories', ['company_id' => $company->id, 'category' => 'pricing']);
    }

    public function test_skill_module_for_retail_industry(): void
    {
        $addon = app(SkillModuleRegistry::class)->promptAddonForCompany('retail');
        $this->assertStringContainsString('Retail Assistant', $addon);
    }

    public function test_executive_dashboard_api(): void
    {
        $company = $this->platformCompany();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/company/executive-ai/dashboard')
            ->assertOk()
            ->assertJsonStructure(['worldModel', 'topDecisions', 'openOpportunities', 'pendingApprovals']);
    }

    public function test_trust_log_created_by_orchestrator(): void
    {
        $company = $this->platformCompany();
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254711122233',
            'customer_name' => 'Test',
        ]);

        $mock = \Mockery::mock(\App\Services\Agent\AgentChatService::class);
        $mock->shouldReceive('completeWithTools')->once()->andReturn(new \App\Services\AI\OpenAiChatResult(
            content: 'Hello! How can I help you today?',
            success: true,
            model: 'gpt-4o-mini',
        ));
        $this->app->instance(\App\Services\Agent\AgentChatService::class, $mock);

        app(\App\Services\Agent\CommerceAgentOrchestrator::class)->run(
            $company, $chat, '254711122233', 'Test', 'Hi',
        );

        $this->assertGreaterThanOrEqual(1, AgentTrustLog::where('company_id', $company->id)->count());
    }
}
