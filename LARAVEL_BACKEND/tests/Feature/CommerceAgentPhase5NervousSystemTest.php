<?php

namespace Tests\Feature;

use App\Models\AgentActionRequest;
use App\Models\BusinessGraphNode;
use App\Models\BusinessTimelineEvent;
use App\Models\CommerceAgentEvent;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\Agent\Events\CommerceEventDetector;
use App\Services\Agent\Onboarding\OnboardingInterviewService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentPhase5NervousSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        config([
            'agent.company.reasoning_enabled' => false,
            'agent.owner_analytics.use_llm' => false,
        ]);
    }

    /** @return array{company: Company, owner: User} */
    private function nervousCompany(): array
    {
        $company = Company::create([
            'name' => 'Nervous Co',
            'email' => 'nervous@test.local',
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
            'agent_proactive_enabled' => true,
        ]);
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'ph-ns',
            'whatsapp_business_account_id' => 'waba-ns',
            'access_token' => 'tok',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return ['company' => $company, 'owner' => $owner];
    }

    public function test_mission_control_api(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->nervousCompany();
        Sanctum::actingAs($owner);

        CommerceAgentEvent::create([
            'company_id' => $company->id,
            'event_type' => 'low_stock',
            'event_key' => 'low_stock:product:1',
            'payload' => ['summary' => 'Widget low stock'],
            'status' => 'open',
        ]);
        AgentActionRequest::create([
            'company_id' => $company->id,
            'action_type' => 'send_campaign',
            'risk_level' => 'medium',
            'reasoning' => 'Promote slow movers',
            'status' => 'pending',
            'payload' => [],
        ]);

        $response = $this->getJson('/api/company/mission-control');
        $response->assertOk();
        $response->assertJsonStructure([
            'generatedAt',
            'attentionQueue',
            'counts' => ['openEvents', 'pendingApprovals', 'openOpportunities'],
            'recentTimeline',
            'graphStats' => ['nodes', 'edges'],
        ]);
        $this->assertGreaterThanOrEqual(1, count($response->json('attentionQueue')));
    }

    public function test_business_timeline_sync_and_list(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->nervousCompany();
        Sanctum::actingAs($owner);

        Product::create([
            'company_id' => $company->id,
            'name' => 'Timeline Widget',
            'price' => 100,
            'stock' => 2,
            'status' => 'active',
        ]);

        CommerceAgentEvent::create([
            'company_id' => $company->id,
            'event_type' => 'low_stock',
            'event_key' => 'low_stock:product:99',
            'payload' => ['summary' => 'Timeline sync test'],
            'status' => 'open',
        ]);

        $sync = $this->postJson('/api/company/business-timeline/sync');
        $sync->assertCreated();
        $this->assertGreaterThan(0, $sync->json('synced'));

        $list = $this->getJson('/api/company/business-timeline');
        $list->assertOk();
        $this->assertGreaterThan(0, count($list->json('events')));
        $this->assertDatabaseHas('business_timeline_events', ['company_id' => $company->id]);
    }

    public function test_business_graph_sync_and_manual_node(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->nervousCompany();
        Sanctum::actingAs($owner);

        Product::create([
            'company_id' => $company->id,
            'name' => 'Graph Widget',
            'price' => 50,
            'stock' => 20,
            'status' => 'active',
            'category' => 'Gadgets',
        ]);

        $sync = $this->postJson('/api/company/business-graph/sync');
        $sync->assertCreated();
        $this->assertGreaterThan(0, $sync->json('stats.nodes'));

        $node = $this->postJson('/api/company/business-graph/nodes', [
            'nodeType' => BusinessGraphNode::TYPE_SUPPLIER,
            'label' => 'Acme Supplies',
        ]);
        $node->assertCreated();

        $graph = $this->getJson('/api/company/business-graph');
        $graph->assertOk();
        $this->assertGreaterThan(0, count($graph->json('nodes')));
    }

    public function test_commerce_event_detector_records_timeline(): void
    {
        ['company' => $company] = $this->nervousCompany();

        Product::create([
            'company_id' => $company->id,
            'name' => 'Low Stock Item',
            'price' => 10,
            'stock' => 1,
            'status' => 'active',
        ]);

        app(CommerceEventDetector::class)->detectForCompany($company);

        $this->assertGreaterThan(0, CommerceAgentEvent::where('company_id', $company->id)->count());
        $this->assertGreaterThan(0, BusinessTimelineEvent::where('company_id', $company->id)->count());
    }

    public function test_onboarding_interview_start_and_respond(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->nervousCompany();
        Sanctum::actingAs($owner);

        $start = $this->postJson('/api/company/onboarding-interview/start');
        $start->assertCreated();
        $sessionId = $start->json('sessionId');
        $this->assertNotEmpty($sessionId);

        $respond = $this->postJson('/api/company/onboarding-interview/respond', [
            'sessionId' => $sessionId,
            'message' => str_repeat('We sell handmade jewelry to millennials online. ', 8),
        ]);
        $respond->assertOk();
        $respond->assertJsonPath('complete', true);

        Cache::flush();
    }

    public function test_owner_investigation_records_timeline(): void
    {
        ['owner' => $owner] = $this->nervousCompany();
        Sanctum::actingAs($owner);

        $this->postJson('/api/company/owner-analytics/investigate', [
            'question' => 'Why are sales flat?',
            'period' => '7d',
        ])->assertCreated();

        $this->assertGreaterThan(0, BusinessTimelineEvent::where('event_type', 'investigation')->count());
    }
}
