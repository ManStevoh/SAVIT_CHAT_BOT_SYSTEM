<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Agent\AgentChatService;
use App\Services\Agent\AgentToolRegistry;
use App\Services\Agent\BusinessGoalService;
use App\Services\Agent\CommerceAgentOrchestrator;
use App\Services\Agent\CommerceAgentReplyService;
use App\Services\AI\OpenAiChatResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CommerceAgentTest extends TestCase
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

    public function test_tool_registry_registers_all_commerce_tools(): void
    {
        $registry = app(AgentToolRegistry::class);
        $names = array_map(fn ($t) => $t->name(), $registry->all());

        $this->assertContains('search_products', $names);
        $this->assertContains('process_order_message', $names);
        $this->assertContains('get_product_relationships', $names);
        $this->assertContains('get_weather', $names);
        $this->assertCount(20, $names);
    }

    public function test_agent_mode_off_when_setting_false(): void
    {
        $company = Company::create(['name' => 'Test Co', 'email' => 't@test.local', 'status' => 'active']);
        CompanySetting::create([
            'company_id' => $company->id,
            'auto_reply_enabled' => true,
            'agent_commerce_enabled' => false,
        ]);
        $company->load('settings');

        $this->assertFalse(CommerceAgentReplyService::isEnabledForCompany($company));
    }

    public function test_agent_mode_enabled_when_company_setting_on(): void
    {
        $company = Company::create(['name' => 'Test Co', 'email' => 't@test.local', 'status' => 'active']);
        CompanySetting::create([
            'company_id' => $company->id,
            'auto_reply_enabled' => true,
            'agent_commerce_enabled' => true,
        ]);
        $company->load('settings');

        $this->assertTrue(CommerceAgentReplyService::isEnabledForCompany($company));
    }

    public function test_customer_memory_upsert_and_retrieve(): void
    {
        $company = Company::create(['name' => 'Test Co', 'email' => 't@test.local', 'status' => 'active']);
        $service = app(\App\Services\Agent\CustomerMemoryService::class);

        $service->upsert($company->id, '254712345678', 'preferred_brand', 'Samsung', 'preference');
        $prompt = $service->getForPrompt($company->id, '254712345678');

        $this->assertStringContainsString('Samsung', $prompt);
        $this->assertStringContainsString('preferred_brand', $prompt);
    }

    public function test_business_goals_in_prompt(): void
    {
        $company = Company::create(['name' => 'Test Co', 'email' => 't@test.local', 'status' => 'active']);
        CompanySetting::create([
            'company_id' => $company->id,
            'agent_business_goals' => ['increase_revenue', 'reduce_refunds'],
        ]);
        $company->load('settings');

        $prompt = app(BusinessGoalService::class)->getForPrompt($company);

        $this->assertStringContainsString('increase_revenue', $prompt);
        $this->assertStringContainsString('reduce_refunds', $prompt);
    }

    public function test_orchestrator_returns_agent_reply_from_llm(): void
    {
        $company = Company::create(['name' => 'Test Co', 'email' => 't@test.local', 'status' => 'active']);
        CompanySetting::create([
            'company_id' => $company->id,
            'auto_reply_enabled' => true,
            'agent_commerce_enabled' => true,
            'ai_greeting' => 'Welcome',
            'ai_tone' => 'balanced',
        ]);
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254712345678',
            'customer_name' => 'Stephen',
            'status' => 'open',
        ]);
        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Do you have Samsung projectors?',
            'sender' => 'customer',
            'status' => 'received',
        ]);

        $mock = Mockery::mock(AgentChatService::class);
        $mock->shouldReceive('completeWithTools')
            ->once()
            ->andReturn(new OpenAiChatResult(
                content: 'Yes! We have several Samsung projectors in stock. Would you like recommendations?',
                success: true,
                model: 'gpt-4o-mini',
                toolCalls: [],
            ));
        $this->app->instance(AgentChatService::class, $mock);

        $result = app(CommerceAgentOrchestrator::class)->run(
            $company,
            $chat,
            '254712345678',
            'Stephen',
            'Do you have Samsung projectors?',
        );

        $this->assertSame('agent_os', $result['route']);
        $this->assertStringContainsString('Samsung', $result['reply'] ?? '');
    }
}
