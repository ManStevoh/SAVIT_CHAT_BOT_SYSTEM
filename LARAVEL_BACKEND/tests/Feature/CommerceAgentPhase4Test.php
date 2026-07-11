<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\CommerceAgentEvent;
use App\Models\Company;
use App\Models\CompanyBrainSnapshot;
use App\Models\CompanyNotification;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\MessageVisionAnalysis;
use App\Models\Order;
use App\Models\OwnerAnalyticsInvestigation;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\AgentToolRegistry;
use App\Services\Agent\Brain\UnifiedCompanyBrainService;
use App\Services\Agent\Events\CommerceEventHandler;
use App\Services\Agent\Owner\OwnerAnalyticsAgentService;
use App\Services\Agent\Tools\CheckCalendarAvailabilityTool;
use App\Services\Agent\Tools\CheckMpesaPaymentTool;
use App\Services\Agent\Tools\GetMarketingPerformanceTool;
use App\Services\Agent\Tools\GetShippingQuoteTool;
use App\Services\Agent\Vision\VisionPipelineService;
use App\Services\AI\AiModelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentPhase4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'agent.company.reasoning_enabled' => false,
            'agent.specialists.use_llm' => false,
            'agent.owner_analytics.use_llm' => true,
        ]);
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $provider->update(['api_key' => 'sk-phase4', 'is_enabled' => true]);
        AiModelResolver::clearCache();
    }

    private function phase4Company(): Company
    {
        $company = Company::create([
            'name' => 'Phase4 Co',
            'email' => 'phase4@test.local',
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
            'orders_accept_mpesa' => true,
            'timezone' => 'Africa/Nairobi',
            'working_hours' => [
                ['day' => 'monday', 'enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                ['day' => 'tuesday', 'enabled' => true, 'open' => '09:00', 'close' => '18:00'],
            ],
        ]);
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'ph-p4',
            'whatsapp_business_account_id' => 'waba-p4',
            'access_token' => 'tok',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        return $company->fresh(['settings']);
    }

    public function test_tool_registry_has_eighteen_tools(): void
    {
        $names = array_map(fn ($t) => $t->name(), app(AgentToolRegistry::class)->all());
        $this->assertCount(20, $names);
        $this->assertContains('check_mpesa_payment', $names);
        $this->assertContains('get_shipping_quote', $names);
        $this->assertContains('check_calendar_availability', $names);
        $this->assertContains('get_marketing_performance', $names);
    }

    public function test_vision_pipeline_analyzes_image_and_matches_product(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'scene_summary' => 'Smartphone in box',
                        'detected_products' => ['Phone X Pro'],
                        'warranty_card_detected' => false,
                        'warranty_details' => '',
                        'receipt_detected' => false,
                        'damage_visible' => false,
                        'confidence' => 0.88,
                    ])],
                ]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
            ], 200),
        ]);

        $company = $this->phase4Company();
        Product::create([
            'company_id' => $company->id,
            'name' => 'Phone X Pro',
            'price' => 45000,
            'stock' => 10,
            'status' => 'active',
        ]);
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000099',
            'customer_name' => 'Cam',
        ]);
        $message = Message::create([
            'chat_id' => $chat->id,
            'content' => '[image received]',
            'message_type' => 'image',
            'attachment_url' => '/storage/chat-attachments/incoming/test-phone.jpg',
            'sender' => 'customer',
            'status' => 'received',
        ]);

        $analysis = app(VisionPipelineService::class)->analyzeMessage($message);

        $this->assertNotNull($analysis);
        $this->assertSame('product', $analysis->analysis_type);
        $this->assertNotEmpty($analysis->product_matches);
        $this->assertDatabaseHas('message_vision_analyses', ['message_id' => $message->id]);
    }

    public function test_unified_brain_snapshot_bridges_commerce_and_growth(): void
    {
        $company = $this->phase4Company();
        Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-P4-1',
            'customer_name' => 'Buyer One',
            'customer_phone' => '254711100001',
            'total' => 8000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $snapshot = app(UnifiedCompanyBrainService::class)->buildSnapshot($company);

        $this->assertNotNull($snapshot->summary_text);
        $this->assertNotEmpty($snapshot->commerce_data);
        $this->assertNotEmpty($snapshot->growth_data);
        $this->assertDatabaseHas('company_brain_snapshots', ['company_id' => $company->id]);
    }

    public function test_owner_analytics_investigates_sales_question(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'findings' => [
                            ['claim' => 'Revenue declined 25% vs prior period.', 'evidence_key' => 'orders.revenue_change_pct', 'severity' => 'high'],
                        ],
                        'recommendations' => ['Review ad spend and follow up abandoned carts.'],
                        'confidence' => 0.82,
                        'executive_summary' => 'Sales are down primarily due to fewer paid orders this period.',
                    ])],
                ]],
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 80, 'total_tokens' => 280],
            ], 200),
        ]);

        $company = $this->phase4Company();
        Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-P4-CUR',
            'customer_name' => 'Buyer Two',
            'customer_phone' => '254711100002',
            'total' => 2000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created_at' => now()->subDays(3),
        ]);
        Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-P4-OLD',
            'customer_name' => 'Buyer Three',
            'customer_phone' => '254711100003',
            'total' => 10000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'created_at' => now()->subDays(40),
        ]);

        $investigation = app(OwnerAnalyticsAgentService::class)->investigate(
            $company,
            'Why are sales down this month?',
            '30d',
        );

        $this->assertNotEmpty($investigation->findings);
        $this->assertNotEmpty($investigation->evidence);
        $this->assertDatabaseHas('owner_analytics_investigations', [
            'company_id' => $company->id,
            'question' => 'Why are sales down this month?',
        ]);
    }

    public function test_check_mpesa_payment_tool(): void
    {
        $company = $this->phase4Company();
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254711100004',
            'customer_name' => 'Payer',
        ]);
        Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-MPESA-1',
            'customer_name' => 'Payer',
            'customer_phone' => '254711100004',
            'total' => 1500,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $context = new AgentToolContext($company, $chat, '254711100004', 'Payer', 'payment?');
        $result = app(CheckMpesaPaymentTool::class)->execute($context, ['order_number' => 'ORD-MPESA-1']);

        $this->assertTrue($result['found']);
        $this->assertSame('pending', $result['payment_status']);
        $this->assertTrue($result['mpesa_accepted']);
    }

    public function test_get_shipping_quote_tool(): void
    {
        $company = $this->phase4Company();
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254711100005',
            'customer_name' => 'Ship',
        ]);
        $context = new AgentToolContext($company, $chat, '254711100005', 'Ship', 'delivery cost?');
        $result = app(GetShippingQuoteTool::class)->execute($context, [
            'destination' => 'Nairobi CBD',
            'order_total' => 3000,
        ]);

        $this->assertNotNull($result['quote']);
        $this->assertSame('heuristic', $result['source']);
    }

    public function test_check_calendar_availability_tool(): void
    {
        $company = $this->phase4Company();
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254711100006',
            'customer_name' => 'Book',
        ]);
        $context = new AgentToolContext($company, $chat, '254711100006', 'Book', 'appointment?');
        $result = app(CheckCalendarAvailabilityTool::class)->execute($context, ['days_ahead' => 3]);

        $this->assertCount(3, $result['slots']);
        $this->assertSame('Africa/Nairobi', $result['timezone']);
    }

    public function test_get_marketing_performance_tool(): void
    {
        $company = $this->phase4Company();
        app(UnifiedCompanyBrainService::class)->buildSnapshot($company);
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254711100007',
            'customer_name' => 'Mkt',
        ]);
        $context = new AgentToolContext($company, $chat, '254711100007', 'Mkt', 'how are ads doing?');
        $result = app(GetMarketingPerformanceTool::class)->execute($context, ['period' => '30d']);

        $this->assertArrayHasKey('executive_summary', $result);
        $this->assertSame('30d', $result['period']);
    }

    public function test_owner_analytics_api(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'findings' => [['claim' => 'Stable sales.', 'evidence_key' => 'orders', 'severity' => 'low']],
                        'recommendations' => [],
                        'confidence' => 0.7,
                    ])],
                ]],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 30, 'total_tokens' => 80],
            ], 200),
        ]);

        $company = $this->phase4Company();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/company/owner-analytics/investigate', [
            'question' => 'Why are sales down?',
            'period' => '30d',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('investigation.question', 'Why are sales down?');
        $this->assertGreaterThan(0, OwnerAnalyticsInvestigation::count());
    }

    public function test_company_brain_api(): void
    {
        $company = $this->phase4Company();
        CompanyBrainSnapshot::create([
            'company_id' => $company->id,
            'snapshot_at' => now(),
            'commerce_data' => ['orders' => ['paid_last_30_days' => 1]],
            'growth_data' => ['executive_summary' => ['leads' => 0]],
            'summary_text' => 'Test brain summary.',
            'digest' => ['revenue_7d' => 1000],
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/company/company-brain');
        $response->assertOk();
        $response->assertJsonPath('snapshot.summaryText', 'Test brain summary.');
    }

    public function test_owner_event_alerts_create_notifications(): void
    {
        $company = $this->phase4Company();
        CommerceAgentEvent::create([
            'company_id' => $company->id,
            'event_type' => 'low_stock',
            'event_key' => 'low_stock:1',
            'payload' => ['summary' => 'Widget A has 2 units left'],
            'status' => 'open',
        ]);

        $alerted = app(CommerceEventHandler::class)->handleOwnerAlerts((int) $company->id);

        $this->assertSame(1, $alerted);
        $this->assertDatabaseHas('company_notifications', [
            'company_id' => $company->id,
            'type' => 'agent',
        ]);
        $this->assertSame('alerted', CommerceAgentEvent::first()->status);
    }
}
