<?php

namespace Tests\Feature;

use App\Models\AgentTrustLog;
use App\Models\BusinessTimelineEvent;
use App\Models\CommerceAgentEvent;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\OwnerAnalyticsInvestigation;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentPhase6Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        config(['agent.owner_analytics.use_llm' => false]);
    }

    /** @return array{company: Company, owner: User} */
    private function phase6Company(): array
    {
        $company = Company::create([
            'name' => 'Phase6 Co',
            'email' => 'phase6@test.local',
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
        CompanySetting::create([
            'company_id' => $company->id,
            'agent_commerce_enabled' => true,
        ]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return ['company' => $company, 'owner' => $owner];
    }

    public function test_agent_trust_logs_api(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->phase6Company();
        Sanctum::actingAs($owner);

        AgentTrustLog::create([
            'company_id' => $company->id,
            'action_type' => 'customer_reply',
            'goal' => 'increase_revenue',
            'reasoning_summary' => 'Recommended product bundle based on order history.',
            'tools_used' => ['search_products', 'get_customer_profile'],
            'data_consulted' => ['orders.last_30d'],
            'confidence' => 0.82,
            'explainability' => ['alternatives' => ['single item upsell']],
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/company/agent-trust-logs');
        $response->assertOk();
        $response->assertJsonCount(1, 'logs');
        $response->assertJsonPath('logs.0.actionType', 'customer_reply');
    }

    public function test_memory_search_finds_timeline_and_investigations(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->phase6Company();
        Sanctum::actingAs($owner);

        BusinessTimelineEvent::create([
            'company_id' => $company->id,
            'event_type' => 'low_stock',
            'category' => 'signal',
            'title' => 'Low stock alert',
            'summary' => 'Widget inventory critically low',
            'occurred_at' => now(),
            'importance' => 80,
        ]);

        OwnerAnalyticsInvestigation::create([
            'company_id' => $company->id,
            'question' => 'Why is inventory low this week?',
            'period' => '7d',
            'status' => 'completed',
            'findings' => [['claim' => 'Stock depletion from weekend sales spike', 'severity' => 'high']],
            'recommendations' => ['Restock top SKUs'],
            'confidence' => 0.7,
        ]);

        CommerceAgentEvent::create([
            'company_id' => $company->id,
            'event_type' => 'low_stock',
            'event_key' => 'test',
            'payload' => [],
            'status' => 'open',
        ]);

        $response = $this->postJson('/api/company/memory-search', [
            'query' => 'inventory low',
            'limit' => 10,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['query', 'results', 'counts' => ['total']]);
        $this->assertGreaterThanOrEqual(1, count($response->json('results')));
    }

    public function test_mission_control_explainability_api(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->phase6Company();
        Sanctum::actingAs($owner);

        $log = AgentTrustLog::create([
            'company_id' => $company->id,
            'action_type' => 'proactive_outreach',
            'reasoning_summary' => 'Follow up on abandoned cart.',
            'confidence' => 0.75,
            'created_at' => now(),
        ]);

        $this->getJson("/api/company/mission-control/explainability/{$log->id}")
            ->assertOk()
            ->assertJsonPath('explainability.action', 'proactive_outreach');
    }
}
