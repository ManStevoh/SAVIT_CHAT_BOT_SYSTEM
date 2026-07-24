<?php

namespace Tests\Feature;

use App\Jobs\Agent\ExtractCustomerMemoriesJob;
use App\Jobs\Agent\ProcessAgentProactiveEventsJob;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\AgentToolInvocation;
use App\Models\AiProvider;
use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\Agent\AgentChatService;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\AgentToolRunner;
use App\Services\Agent\CustomerMemoryExtractionService;
use App\Services\AI\AiModelResolver;
use App\Services\AI\OpenAiChatResult;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * End-to-end verification of Agent Commerce OS flows (executed in CI, not inferred from code).
 */
class CommerceAgentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.company.reasoning_enabled' => false]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function seedOpenAiProvider(): void
    {
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $provider->update(['api_key' => 'sk-test-agent', 'is_enabled' => true]);
        AiModelResolver::clearCache();
    }

    /**
     * @return array{0: Company, 1: Chat, 2: WhatsAppAccount}
     */
    private function agentReadyCompany(array $settingsOverrides = []): array
    {
        $this->seedOpenAiProvider();

        $company = Company::create([
            'name' => 'Agent Flow Co',
            'email' => 'agent-flow@test.local',
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

        CompanySetting::create(array_merge([
            'company_id' => $company->id,
            'auto_reply_enabled' => true,
            'agent_commerce_enabled' => true,
            'agent_proactive_enabled' => true,
            'agent_business_goals' => ['increase_revenue'],
            'learn_from_conversations' => true,
            'ai_greeting' => 'Welcome',
            'ai_tone' => 'balanced',
        ], $settingsOverrides));

        $wa = WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-agent-flow',
            'whatsapp_business_account_id' => 'waba-agent',
            'access_token' => 'wa-token-agent',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254711122233',
            'customer_name' => 'Jane',
        ]);

        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Hello',
            'sender' => 'customer',
            'whatsapp_message_id' => 'wamid.agent.in.0',
        ]);

        return [$company, $chat, $wa];
    }

    public function test_whatsapp_job_uses_agent_path_and_sends_reply(): void
    {
        [$company, $chat] = $this->agentReadyCompany();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.agent.bot.1']]], 200),
        ]);

        $mock = Mockery::mock(AgentChatService::class);
        $mock->shouldReceive('completeWithTools')
            ->once()
            ->andReturn(new OpenAiChatResult(
                content: 'We have great laptops starting at KES 45,000. Want a recommendation?',
                success: true,
                model: 'gpt-4o-mini',
                toolCalls: [],
            ));
        $this->app->instance(AgentChatService::class, $mock);

        Queue::fake();

        $job = new ProcessIncomingWhatsAppMessage(
            $company->id,
            $chat->id,
            '254711122233',
            'phone-agent-flow',
            'Do you sell laptops?',
            'Jane',
            'wamid.agent.in.1',
        );
        $job->handle(
            app(\App\Services\AIReplyService::class),
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\MailService::class),
        );

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'sender' => 'bot',
            'reply_source' => 'agent_os',
        ]);
        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'sender' => 'bot',
            'content' => 'We have great laptops starting at KES 45,000. Want a recommendation?',
        ]);
        Queue::assertPushed(ExtractCustomerMemoriesJob::class, fn ($job) => $job->chatId === $chat->id);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_whatsapp_job_falls_back_to_legacy_when_agent_returns_empty(): void
    {
        [$company, $chat] = $this->agentReadyCompany(['ai_reply_mode' => 'balanced']);

        \App\Models\Faq::create([
            'company_id' => $company->id,
            'question' => 'Hours',
            'answer' => 'We are open 9am to 6pm daily.',
            'keywords' => ['hours', 'open'],
            'is_active' => true,
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.fallback.1']]], 200),
        ]);

        $mock = Mockery::mock(AgentChatService::class);
        $mock->shouldReceive('completeWithTools')
            ->once()
            ->andReturn(new OpenAiChatResult(
                content: null,
                success: false,
                model: 'gpt-4o-mini',
                error: 'Simulated agent failure',
            ));
        $this->app->instance(AgentChatService::class, $mock);

        $job = new ProcessIncomingWhatsAppMessage(
            $company->id,
            $chat->id,
            '254711122233',
            'phone-agent-flow',
            'What are your opening hours?',
            'Jane',
            'wamid.agent.in.2',
        );
        $job->handle(
            app(\App\Services\AIReplyService::class),
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\MailService::class),
        );

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'sender' => 'bot',
            'reply_source' => 'faq',
            'content' => 'We are open 9am to 6pm daily.',
        ]);
    }

    public function test_settings_api_returns_and_saves_agent_fields(): void
    {
        $this->seedOpenAiProvider();
        [$company] = $this->agentReadyCompany(['agent_commerce_enabled' => false, 'agent_proactive_enabled' => false]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/company/settings')
            ->assertOk()
            ->assertJsonPath('agentCommerceEnabled', false)
            ->assertJsonPath('agentProactiveEnabled', false)
            ->assertJsonStructure(['agentBusinessGoalCatalog', 'businessDna', 'businessDnaPresets']);

        $this->putJson('/api/company/settings', [
            'agentCommerceEnabled' => true,
            'agentProactiveEnabled' => true,
            'agentBusinessGoals' => ['increase_revenue', 'reduce_refunds', 'invalid_goal'],
            'businessDna' => [
                'tone' => 'luxury and calm',
                'values' => ['quality', 'discretion'],
                'risk_tolerance' => 'low',
                'service_philosophy' => 'White-glove experience',
                'communication_style' => 'Refined and understated',
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'agent_commerce_enabled' => true,
            'agent_proactive_enabled' => true,
        ]);

        $settings = CompanySetting::where('company_id', $company->id)->first();
        $this->assertSame(['increase_revenue', 'reduce_refunds'], $settings->agent_business_goals);
        $this->assertSame('luxury and calm', $settings->business_dna['tone'] ?? null);
        $this->assertSame(['quality', 'discretion'], $settings->business_dna['values'] ?? null);

        $this->putJson('/api/company/settings', [
            'businessDna' => null,
        ])->assertOk();

        $this->assertNull(CompanySetting::where('company_id', $company->id)->first()->business_dna);
    }

    public function test_search_products_tool_finds_product_and_audits_invocation(): void
    {
        [$company, $chat] = $this->agentReadyCompany();

        Product::create([
            'company_id' => $company->id,
            'name' => 'Samsung SP200 Projector',
            'description' => 'Bright classroom projector',
            'price' => 85000,
            'stock' => 5,
            'status' => 'active',
        ]);

        $context = new AgentToolContext($company, $chat, '254711122233', 'Jane', 'projector');
        $result = app(AgentToolRunner::class)->run('search_products', $context, ['query' => 'Samsung', 'limit' => 5]);

        $this->assertNotEmpty($result['products'] ?? []);
        $this->assertStringContainsString('Samsung', $result['products'][0]['name'] ?? '');
        $this->assertDatabaseHas('agent_tool_invocations', [
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'tool_name' => 'search_products',
            'success' => true,
        ]);
    }

    public function test_remember_customer_tool_persists_memory(): void
    {
        [$company, $chat] = $this->agentReadyCompany();

        $context = new AgentToolContext($company, $chat, '254711122233', 'Jane', 'I prefer Samsung');
        $result = app(AgentToolRunner::class)->run('remember_customer', $context, [
            'key' => 'preferred_brand',
            'value' => 'Samsung',
            'category' => 'preference',
        ]);

        $this->assertTrue($result['stored'] ?? false);
        $this->assertDatabaseHas('customer_memories', [
            'company_id' => $company->id,
            'customer_phone' => '254711122233',
            'memory_key' => 'preferred_brand',
            'memory_value' => 'Samsung',
        ]);
    }

    public function test_proactive_job_sends_abandoned_cart_follow_up(): void
    {
        [$company, $chat] = $this->agentReadyCompany();

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-AGENT-001',
            'customer_name' => 'Jane',
            'customer_phone' => '254711122233',
            'total' => 1500,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $order->created_at = now()->subHours(30);
        $order->save();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => 'Hi Jane! Your order ORD-AGENT-001 is waiting — need help paying?']]],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 20, 'total_tokens' => 70],
            ], 200),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.proactive.1']]], 200),
        ]);

        (new ProcessAgentProactiveEventsJob($company->id))->handle(
            app(\App\Services\Agent\AgentProactiveMessageService::class),
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\Agent\Company\CustomerIntentChainService::class),
            app(\App\Services\Agent\Events\CommerceEventDetector::class),
            app(\App\Services\Agent\Events\CommerceEventHandler::class),
        );

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'sender' => 'bot',
        ]);
        $order->refresh();
        $this->assertNotNull($order->agent_proactive_follow_up_at);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_payment_confirmation_uses_proactive_message_when_enabled(): void
    {
        [$company, $chat] = $this->agentReadyCompany();

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-PAY-001',
            'customer_name' => 'Jane',
            'customer_phone' => '254711122233',
            'total' => 2500,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => 'Thank you Jane! Payment for ORD-PAY-001 received — we appreciate your business.']]],
                'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 15, 'total_tokens' => 55],
            ], 200),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.pay.1']]], 200),
        ]);

        app(OrderPaymentService::class)->markOrderPaid($order);

        $botMessage = Message::where('chat_id', $chat->id)->where('sender', 'bot')->latest('id')->first();
        $this->assertNotNull($botMessage);
        $this->assertStringContainsString('ORD-PAY-001', $botMessage->content);
        $this->assertStringContainsString('Jane', $botMessage->content);
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_memory_extraction_stores_customer_facts(): void
    {
        [$company, $chat] = $this->agentReadyCompany();
        $company->load('settings');

        for ($i = 0; $i < 4; $i++) {
            Message::create([
                'chat_id' => $chat->id,
                'content' => $i % 2 === 0 ? 'I run a school in Nairobi' : 'I need 50 Samsung projectors',
                'sender' => $i % 2 === 0 ? 'customer' : 'bot',
            ]);
        }

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'memories' => [
                            ['key' => 'location', 'value' => 'Nairobi', 'category' => 'location'],
                            ['key' => 'business_type', 'value' => 'school', 'category' => 'context'],
                        ],
                    ])],
                ]],
                'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 40, 'total_tokens' => 120],
            ], 200),
        ]);

        $stored = app(CustomerMemoryExtractionService::class)->extractFromChat($company, $chat, '254711122233');

        $this->assertSame(2, $stored);
        $this->assertDatabaseHas('customer_memories', [
            'company_id' => $company->id,
            'memory_key' => 'location',
            'memory_value' => 'Nairobi',
        ]);
    }

    public function test_agent_tool_loop_records_multiple_invocations(): void
    {
        [$company, $chat] = $this->agentReadyCompany();

        Product::create([
            'company_id' => $company->id,
            'name' => 'HP Laptop 840',
            'price' => 65000,
            'stock' => 10,
            'status' => 'active',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([], 200),
        ]);

        $toolCallId = 'call_test_123';
        $mock = Mockery::mock(AgentChatService::class);
        $mock->shouldReceive('completeWithTools')
            ->twice()
            ->andReturn(
                new OpenAiChatResult(
                    content: null,
                    success: true,
                    model: 'gpt-4o-mini',
                    toolCalls: [[
                        'id' => $toolCallId,
                        'name' => 'search_products',
                        'arguments' => json_encode(['query' => 'HP']),
                    ]],
                    finishReason: 'tool_calls',
                ),
                new OpenAiChatResult(
                    content: 'We have HP Laptop 840 in stock at KES 65,000.',
                    success: true,
                    model: 'gpt-4o-mini',
                    toolCalls: [],
                ),
            );
        $this->app->instance(AgentChatService::class, $mock);

        $result = app(\App\Services\Agent\CommerceAgentOrchestrator::class)->run(
            $company,
            $chat,
            '254711122233',
            'Jane',
            'Any laptops?',
        );

        $this->assertSame('agent_os', $result['route']);
        $this->assertGreaterThanOrEqual(1, AgentToolInvocation::where('company_id', $company->id)->count());
    }
}
