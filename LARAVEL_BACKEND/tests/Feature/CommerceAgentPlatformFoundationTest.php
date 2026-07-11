<?php

namespace Tests\Feature;

use App\Models\AgentActionRequest;
use App\Models\AiProvider;
use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\DomainEvent;
use App\Models\InvestigationCase;
use App\Models\IntelligenceOutcome;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\Agent\Intelligence\IntelligenceReasoningService;
use App\Services\Agent\Platform\AgentApprovalService;
use App\Services\AI\AiModelResolver;
use App\Services\Platform\DomainEventDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentPlatformFoundationTest extends TestCase
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
        $provider->update(['api_key' => 'sk-foundation', 'is_enabled' => true]);
        AiModelResolver::clearCache();
        PlatformSetting::first()?->update(['audit_logging_enabled' => true]);
    }

    private function foundationCompany(): Company
    {
        $company = Company::create([
            'name' => 'Foundation Co',
            'email' => 'foundation@test.local',
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
            'phone_number_id' => 'ph-found',
            'whatsapp_business_account_id' => 'waba-found',
            'access_token' => 'tok',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        return $company->fresh(['settings']);
    }

    public function test_reasoning_opens_investigation_case_and_probability_scores(): void
    {
        $company = $this->foundationCompany();
        Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-F-1',
            'customer_phone' => '254700000099',
            'customer_name' => 'Buyer',
            'total' => 8000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $result = app(IntelligenceReasoningService::class)->reason($company, [
            'goal' => 'Why are sales flat this month?',
            'period' => '30d',
        ]);

        $this->assertArrayHasKey('case_id', $result);
        $this->assertArrayHasKey('probability_scores', $result);
        $this->assertNotNull($result['case_id']);
        $this->assertArrayHasKey('buy', $result['probability_scores']);

        $case = InvestigationCase::find($result['case_id']);
        $this->assertNotNull($case);
        $this->assertSame('open', $case->status);
        $this->assertCount(4, $case->steps);

        $this->assertGreaterThan(0, IntelligenceOutcome::where('company_id', $company->id)->count());
        $this->assertSame(1, DomainEvent::where('event_type', 'intelligence.reasoned')->count());
    }

    public function test_intelligence_cases_and_outcome_api(): void
    {
        $company = $this->foundationCompany();
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($owner);

        $reason = $this->postJson('/api/company/intelligence/reason', [
            'goal' => 'Should we discount 10%?',
            'period' => '30d',
        ]);
        $reason->assertCreated();
        $investigationId = $reason->json('reasoning.investigation_id');
        $caseId = $reason->json('reasoning.case_id');

        $cases = $this->getJson('/api/company/intelligence/cases');
        $cases->assertOk();
        $cases->assertJsonPath('cases.0.id', $caseId);

        $show = $this->getJson('/api/company/intelligence/cases/'.$caseId);
        $show->assertOk();
        $show->assertJsonPath('case.goal', 'Should we discount 10%?');

        $outcome = $this->postJson('/api/company/intelligence/outcomes', [
            'source_type' => 'investigation',
            'source_id' => $investigationId,
            'recommended_action' => 'Run a limited 10% discount on slow movers',
            'outcome' => 'positive',
            'notes' => 'Revenue up 8% week over week',
        ]);
        $outcome->assertCreated();
        $outcome->assertJsonPath('outcome.outcome', 'positive');

        $this->assertSame('closed', InvestigationCase::find($caseId)?->status);
    }

    public function test_approval_policy_blocks_non_owner_for_refund(): void
    {
        $company = $this->foundationCompany();
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_user',
            'email_verified_at' => now(),
        ]);

        Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-REF-1',
            'customer_phone' => '254711111111',
            'customer_name' => 'Refund Me',
            'total' => 12000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $request = AgentActionRequest::create([
            'company_id' => $company->id,
            'action_type' => 'issue_order_refund',
            'risk_level' => 'high',
            'payload' => ['arguments' => ['order_number' => 'ORD-REF-1']],
            'status' => 'pending',
        ]);

        $result = app(AgentApprovalService::class)->approve($request, $agent);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('company_owner', strtolower($result['message'] ?? ''));
    }

    public function test_audit_log_written_on_approval_queue(): void
    {
        $company = $this->foundationCompany();

        app(AgentApprovalService::class)->queue(
            $company->id,
            null,
            'send_whatsapp_campaign',
            'high',
            ['campaign_id' => 1],
            'Test campaign',
        );

        $this->assertSame(1, AuditEvent::where('action', 'agent.approval.queued')->count());
    }

    public function test_domain_event_dispatcher_processes_pending(): void
    {
        $company = $this->foundationCompany();
        $dispatcher = app(DomainEventDispatcher::class);
        $dispatcher->dispatch('test.event', ['foo' => 'bar'], $company->id);

        $processed = $dispatcher->processPending();
        $this->assertSame(1, $processed);
        $this->assertSame('dispatched', DomainEvent::first()?->status);
    }
}
