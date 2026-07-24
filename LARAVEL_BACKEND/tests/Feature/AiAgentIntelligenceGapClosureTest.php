<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\AI\AnthropicToolPayloadConverter;
use App\Services\AI\GeminiToolPayloadConverter;
use App\Services\AI\ReplyGuardService;
use App\Services\Agent\AgentCommerceProvisioningService;
use App\Services\Agent\Cognitive\SelfCritiqueService;
use App\Services\Platform\EntitlementService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentIntelligenceGapClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_growth_plan_entitles_agent_commerce_and_provisions_setting(): void
    {
        $company = Company::create([
            'name' => 'Growth AI Co',
            'email' => 'growth-ai@test.local',
            'status' => 'active',
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);

        $this->assertTrue(app(EntitlementService::class)->limitsForCompany($company)['agent_commerce']);

        $settings = app(AgentCommerceProvisioningService::class)->syncForCompany($company);
        $this->assertTrue($settings->agent_commerce_enabled);
    }

    public function test_starter_plan_entitles_and_provisions_agent_commerce(): void
    {
        $company = Company::create([
            'name' => 'Starter AI Co',
            'email' => 'starter-ai@test.local',
            'status' => 'active',
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'status' => 'trial',
            'start_date' => now(),
            'end_date' => now()->addDays(14),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);

        $this->assertTrue(app(EntitlementService::class)->limitsForCompany($company)['agent_commerce']);
        $settings = app(AgentCommerceProvisioningService::class)->syncForCompany($company);
        $this->assertTrue((bool) $settings->agent_commerce_enabled);
    }

    public function test_reply_guard_and_self_critique_evals(): void
    {
        $company = Company::create([
            'name' => 'Guard Co',
            'email' => 'guard@test.local',
            'status' => 'active',
        ]);
        Product::create([
            'company_id' => $company->id,
            'name' => 'Tea Set',
            'price' => 40,
            'stock' => 0,
            'status' => 'active',
            'category' => 'Home',
        ]);

        $guard = app(ReplyGuardService::class);
        $this->assertStringContainsString('see catalog for price', $guard->guard($company, 'Tea Set costs 777'));
        $this->assertStringContainsString('out of stock', mb_strtolower($guard->guard($company, 'Tea Set is in stock now')));

        $critique = app(SelfCritiqueService::class);
        $review = $critique->review($company, 'Payment failed.', [
            'perception' => ['emotion' => 'angry', 'topic' => 'order'],
        ]);
        $this->assertNotNull($review['rewritten']);
        $this->assertStringContainsString('sorry', mb_strtolower((string) $review['rewritten']));
    }

    public function test_anthropic_and_gemini_tool_converters(): void
    {
        $openaiTool = [[
            'type' => 'function',
            'function' => [
                'name' => 'search_products',
                'description' => 'Search',
                'parameters' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]],
            ],
        ]];

        $anthropic = (new AnthropicToolPayloadConverter)->tools($openaiTool);
        $this->assertSame('search_products', $anthropic[0]['name']);

        $messages = (new AnthropicToolPayloadConverter)->messages([
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'Hi'],
            [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_1',
                    'function' => ['name' => 'search_products', 'arguments' => '{"q":"mug"}'],
                ]],
            ],
            ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '{"ok":true}'],
        ]);
        $this->assertNotSame('', $messages['system']);
        $this->assertNotEmpty($messages['messages']);

        $gemini = (new GeminiToolPayloadConverter)->toolConfig($openaiTool);
        $this->assertSame('search_products', $gemini['functionDeclarations'][0]['name']);
    }

    public function test_artisan_ai_eval_agent_passes(): void
    {
        $this->artisan('ai:eval-agent')->assertSuccessful();
    }

    public function test_reasoning_enabled_by_default_in_config(): void
    {
        $this->assertTrue((bool) config('agent.company.reasoning_enabled'));
        $this->assertFalse((bool) config('agent.specialists.consult_on_turn'));
    }
}
