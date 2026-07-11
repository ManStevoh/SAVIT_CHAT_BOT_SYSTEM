<?php

namespace Tests\Feature;

use App\Models\AgentOperatingGuide;
use App\Models\AgentReasoningTrace;
use App\Models\AiProvider;
use App\Models\Chat;
use App\Models\CommerceBrief;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CustomerIntentChain;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\AgentToolRunner;
use App\Services\Agent\Company\CommerceMorningBriefService;
use App\Services\Agent\Company\ConversationReflectionService;
use App\Services\Agent\Company\MessageSentimentService;
use App\Services\Agent\Company\ReasoningEngineService;
use App\Services\AI\AiModelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AI Company OS — reasoning, graph, sentiment, brief, reflection (verified by execution).
 */
class CommerceAgentCompanyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $provider->update(['api_key' => 'sk-test-company', 'is_enabled' => true]);
        AiModelResolver::clearCache();
    }

    private function companyWithAgent(): Company
    {
        $company = Company::create(['name' => 'AI Co', 'email' => 'ai-co@test.local', 'status' => 'active']);
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
            'auto_reply_enabled' => true,
            'agent_commerce_enabled' => true,
            'agent_proactive_enabled' => true,
            'learn_from_conversations' => true,
            'digital_twin' => [
                'mission' => 'Equip schools across East Africa',
                'brand_voice' => 'Professional and warm',
            ],
        ]);
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'ph-co',
            'whatsapp_business_account_id' => 'waba-co',
            'access_token' => 'tok',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        return $company->fresh(['settings']);
    }

    public function test_sentiment_detects_frustration(): void
    {
        $service = app(MessageSentimentService::class);
        $result = $service->detect('This is taking forever! I am very disappointed.');

        $this->assertSame('frustrated', $result['label']);
        $this->assertNotEmpty($result['cues']);
    }

    public function test_trace_customer_graph_links_orders_to_catalog(): void
    {
        $company = $this->companyWithAgent();
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700111222',
            'customer_name' => 'Stephen',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-G-1',
            'customer_phone' => '254700111222',
            'customer_name' => 'Stephen',
            'total' => 5000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
        OrderProduct::create(['order_id' => $order->id, 'name' => 'HP Laptop 840', 'quantity' => 1, 'price' => 5000]);

        Product::create([
            'company_id' => $company->id,
            'name' => 'HP Laptop Charger',
            'price' => 2500,
            'stock' => 8,
            'status' => 'active',
            'category' => 'accessories',
        ]);

        $context = new AgentToolContext($company, $chat, '254700111222', 'Stephen', 'charger for my laptop');
        $result = app(AgentToolRunner::class)->run('trace_customer_graph', $context, ['query' => 'HP']);

        $this->assertNotEmpty($result['graph']['orders'] ?? []);
        $this->assertStringContainsString('HP Laptop 840', implode(' ', $result['graph']['past_product_names'] ?? []));
    }

    public function test_reasoning_engine_stores_trace_and_updates_intent_chain(): void
    {
        $company = $this->companyWithAgent();
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700111222',
            'customer_name' => 'Stephen',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'understanding' => 'Customer wants to buy 50 laptops for a school',
                        'hypotheses' => ['Bulk purchase', 'Quote request'],
                        'options' => [['label' => 'A', 'approach' => 'Quote bulk price', 'pros' => 'fast', 'cons' => 'none']],
                        'chosen_plan' => 'Gather quantity and delivery date, then search catalog',
                        'specialist_council' => [
                            'sales' => 'Offer volume discount',
                            'support' => 'Confirm warranty terms',
                            'logistics' => 'Ask delivery location',
                        ],
                        'time_context' => 'May need delivery before term starts',
                        'geo_context' => 'Confirm branch/warehouse',
                    ])],
                ]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 80, 'total_tokens' => 180],
            ], 200),
        ]);

        $out = app(ReasoningEngineService::class)->reason(
            $company,
            $chat,
            '254700111222',
            'Stephen',
            'I need 50 laptops for my school next week',
        );

        $this->assertNotEmpty($out['prompt_block']);
        $this->assertDatabaseHas('agent_reasoning_traces', ['company_id' => $company->id, 'chat_id' => $chat->id]);
        $this->assertDatabaseHas('customer_intent_chains', [
            'company_id' => $company->id,
            'customer_phone' => '254700111222',
        ]);

        $chat->refresh();
        $this->assertNotNull($chat->detected_sentiment);
    }

    public function test_morning_brief_persists_for_company(): void
    {
        $company = $this->companyWithAgent();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => 'Good morning. Yesterday sales were strong. Consider restocking low items.']]],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 30, 'total_tokens' => 80],
            ], 200),
        ]);

        $brief = app(CommerceMorningBriefService::class)->generateForCompany($company);

        $this->assertInstanceOf(CommerceBrief::class, $brief);
        $this->assertDatabaseHas('commerce_briefs', ['company_id' => $company->id]);
        $this->assertNotEmpty($brief->summary);
    }

    public function test_reflection_creates_operating_guide(): void
    {
        $company = $this->companyWithAgent();
        $chat = Chat::create(['company_id' => $company->id, 'customer_phone' => '254700111222', 'customer_name' => 'Stephen']);

        for ($i = 0; $i < 4; $i++) {
            \App\Models\Message::create([
                'chat_id' => $chat->id,
                'content' => $i % 2 === 0 ? 'Do you charge VAT?' : 'Yes, VAT is included in listed prices.',
                'sender' => $i % 2 === 0 ? 'customer' : 'bot',
            ]);
        }

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'goal_achieved' => true,
                        'customer_satisfaction_estimate' => 'high',
                        'mistakes' => [],
                        'missed_opportunities' => [],
                        'tool_efficiency' => 'ok',
                        'operating_guide_updates' => [
                            ['topic' => 'vat_questions', 'guidance' => 'Always clarify VAT is included in displayed prices'],
                        ],
                        'insight' => 'Customers frequently ask about VAT',
                    ])],
                ]],
                'usage' => ['prompt_tokens' => 60, 'completion_tokens' => 40, 'total_tokens' => 100],
            ], 200),
        ]);

        $ok = app(ConversationReflectionService::class)->reflect($company, $chat);

        $this->assertTrue($ok);
        $this->assertDatabaseHas('agent_operating_guides', [
            'company_id' => $company->id,
            'topic' => 'vat_questions',
        ]);
    }

    public function test_commerce_brief_api_returns_today_brief(): void
    {
        $company = $this->companyWithAgent();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        CommerceBrief::create([
            'company_id' => $company->id,
            'brief_date' => now()->toDateString(),
            'summary' => 'Good morning Stephen. Sales are up.',
            'metrics' => ['sales_yesterday' => 148200],
            'recommendations' => ['Restock printers'],
        ]);

        $this->getJson('/api/company/commerce-brief')
            ->assertOk()
            ->assertJsonPath('brief.summary', 'Good morning Stephen. Sales are up.');
    }
}
