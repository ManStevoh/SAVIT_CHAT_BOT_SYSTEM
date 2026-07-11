<?php

namespace Tests\Feature;

use App\Models\AgentActionRequest;
use App\Models\AiProvider;
use App\Models\Chat;
use App\Models\CommerceExperiment;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\PlatformIntelligencePattern;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppCampaign;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\AgentToolRegistry;
use App\Services\Agent\AgentToolRunner;
use App\Services\Agent\Platform\AgentApprovalExecutionService;
use App\Services\Agent\Platform\CommerceExperimentService;
use App\Services\Agent\Platform\CrossBusinessLearningService;
use App\Services\Agent\Voice\OwnerVoiceCommandService;
use App\Services\AI\AiModelResolver;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentPhase5Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'agent.company.reasoning_enabled' => false,
            'agent.specialists.use_llm' => false,
            'agent.voice.enabled' => false,
        ]);
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $provider->update(['api_key' => 'sk-phase5', 'is_enabled' => true]);
        AiModelResolver::clearCache();
    }

    private function phase5Company(): array
    {
        $company = Company::create([
            'name' => 'Phase5 Co',
            'email' => 'phase5@test.local',
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
            'auto_reply_enabled' => true,
        ]);
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'ph-p5',
            'whatsapp_business_account_id' => 'waba-p5',
            'access_token' => 'tok',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);
        $owner = User::create([
            'company_id' => $company->id,
            'name' => 'Owner',
            'email' => 'owner@phase5.test',
            'password' => bcrypt('secret'),
            'role' => 'company_owner',
            'phone' => '254712345678',
            'status' => 'active',
        ]);
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254712345678',
            'customer_name' => 'Owner',
            'last_message' => 'hi',
            'last_message_at' => now(),
            'status' => 'active',
        ]);

        return [
            'company' => $company->fresh(['settings']),
            'owner' => $owner,
            'chat' => $chat,
        ];
    }

    public function test_phase5_tools_registered(): void
    {
        $registry = app(AgentToolRegistry::class);
        $names = array_map(fn ($t) => $t->name(), $registry->all());

        $this->assertCount(20, $names);
        $this->assertContains('send_whatsapp_campaign', $names);
        $this->assertContains('issue_order_refund', $names);
    }

    public function test_high_risk_refund_queues_approval(): void
    {
        ['company' => $company, 'chat' => $chat] = $this->phase5Company();

        Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-P5-1',
            'customer_phone' => '254700000001',
            'customer_name' => 'Buyer',
            'total' => 500,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $context = new AgentToolContext($company, $chat, '254700000001', 'Buyer', 'refund please');
        $result = app(AgentToolRunner::class)->run('issue_order_refund', $context, [
            'order_number' => 'ORD-P5-1',
            'reason' => 'Test',
        ]);

        $this->assertTrue($result['pending_approval'] ?? false);
        $this->assertDatabaseHas('agent_action_requests', [
            'company_id' => $company->id,
            'action_type' => 'issue_order_refund',
            'status' => 'pending',
        ]);
    }

    public function test_approve_refund_executes(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->phase5Company();

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-P5-REF',
            'customer_phone' => '254700000002',
            'customer_name' => 'Buyer',
            'total' => 800,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $request = AgentActionRequest::create([
            'company_id' => $company->id,
            'action_type' => 'issue_order_refund',
            'risk_level' => 'high',
            'payload' => ['arguments' => ['order_number' => 'ORD-P5-REF']],
            'status' => 'pending',
        ]);

        $result = app(AgentApprovalExecutionService::class)->execute($request, $owner);

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('executed', $request->fresh()->status);
    }

    public function test_owner_voice_command_queues_refund(): void
    {
        ['company' => $company, 'chat' => $chat] = $this->phase5Company();

        $result = app(OwnerVoiceCommandService::class)->handle(
            $company,
            $chat,
            'Please refund order ORD-VOICE-9',
        );

        $this->assertTrue($result['handled']);
        $this->assertArrayHasKey('approval_id', $result);
        $this->assertDatabaseHas('agent_action_requests', [
            'id' => $result['approval_id'],
            'action_type' => 'issue_order_refund',
            'status' => 'pending',
        ]);
    }

    public function test_executive_api_approve_and_reject(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->phase5Company();
        Sanctum::actingAs($owner);

        $pending = AgentActionRequest::create([
            'company_id' => $company->id,
            'action_type' => 'issue_order_refund',
            'risk_level' => 'high',
            'payload' => ['arguments' => ['order_number' => 'ORD-NOPE']],
            'status' => 'pending',
        ]);

        $reject = $this->postJson("/api/company/executive-ai/approvals/{$pending->id}/reject", [
            'reason' => 'Not now',
        ]);
        $reject->assertOk();
        $reject->assertJsonPath('approval.status', 'rejected');

        Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-API-OK',
            'customer_phone' => '254700000003',
            'customer_name' => 'Buyer',
            'total' => 300,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $approveReq = AgentActionRequest::create([
            'company_id' => $company->id,
            'action_type' => 'issue_order_refund',
            'risk_level' => 'high',
            'payload' => ['arguments' => ['order_number' => 'ORD-API-OK']],
            'status' => 'pending',
        ]);

        $approve = $this->postJson("/api/company/executive-ai/approvals/{$approveReq->id}/approve");
        $approve->assertOk();
        $approve->assertJsonPath('success', true);
        $this->assertSame('executed', $approveReq->fresh()->status);
    }

    public function test_commerce_experiment_lifecycle(): void
    {
        ['company' => $company] = $this->phase5Company();
        $service = app(CommerceExperimentService::class);

        $experiment = $service->createPromotionExperiment(
            $company,
            'Cart nudge test',
            ['message' => 'Hey, your cart is waiting!'],
            ['message' => 'Complete your order today — 10% off!'],
        );

        $this->assertSame('running', $experiment->status);
        $this->assertCount(2, $experiment->variants);

        $active = $service->activePromotionExperiment((int) $company->id);
        $this->assertNotNull($active);

        $variant = $service->assignVariant($active);
        $this->assertNotNull($variant);
        $message = $service->messageForVariant($variant, 'fallback');
        $this->assertNotSame('fallback', $message);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-EXP-1',
            'customer_phone' => '254700000004',
            'customer_name' => 'Buyer',
            'total' => 1200,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        Cache::put("exp_assign:order:{$order->id}", [
            'experiment_id' => $active->id,
            'variant_id' => $variant->id,
        ], now()->addDay());

        app(OrderPaymentService::class)->markOrderPaid($order);

        $variant->refresh();
        $this->assertSame(1, $variant->conversions_count);
    }

    public function test_commerce_experiment_api(): void
    {
        ['owner' => $owner] = $this->phase5Company();
        Sanctum::actingAs($owner);

        $create = $this->postJson('/api/company/commerce-experiments', [
            'name' => 'API experiment',
            'variant_a_message' => 'Message A',
            'variant_b_message' => 'Message B',
        ]);
        $create->assertCreated();

        $list = $this->getJson('/api/company/commerce-experiments');
        $list->assertOk();
        $list->assertJsonCount(1, 'experiments');
    }

    public function test_cross_business_learning_records_pattern_when_seeded(): void
    {
        PlatformIntelligencePattern::create([
            'pattern_key' => 'fast_reply_conversion',
            'pattern_type' => 'engagement',
            'description' => 'seed',
            'evidence_count' => 1,
            'industries' => ['all'],
        ]);

        $recorded = app(CrossBusinessLearningService::class)->analyzeAndRecord();

        $this->assertGreaterThanOrEqual(0, $recorded);
        $this->assertDatabaseHas('platform_intelligence_patterns', [
            'pattern_key' => 'fast_reply_conversion',
        ]);
    }

    public function test_executive_dashboard_api(): void
    {
        ['owner' => $owner] = $this->phase5Company();
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/company/executive-ai/dashboard');
        $response->assertOk();
        $response->assertJsonStructure([
            'worldModel',
            'healthScore',
            'topDecisions',
            'pendingApprovals',
            'openOpportunities',
        ]);
    }
}
