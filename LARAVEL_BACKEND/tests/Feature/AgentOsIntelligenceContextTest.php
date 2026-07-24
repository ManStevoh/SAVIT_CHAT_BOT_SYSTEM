<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Product;
use App\Services\Agent\AgentCustomerIntelligenceContext;
use App\Services\AI\SystemPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentOsIntelligenceContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_intelligence_includes_orders_catalog_and_os_rules(): void
    {
        $company = Company::create([
            'name' => 'OS Shop',
            'email' => 'os-shop@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'agent_commerce_enabled' => true,
            'learn_from_conversations' => true,
            'display_currency' => 'KES',
        ]);

        Product::create([
            'company_id' => $company->id,
            'name' => 'Blue Mug',
            'price' => 500,
            'status' => 'active',
            'stock' => 10,
        ]);

        Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-1001',
            'customer_name' => 'Ada',
            'customer_phone' => '+254712345678',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 500,
        ]);

        $block = app(AgentCustomerIntelligenceContext::class)->build(
            $company->fresh(),
            '+254712345678',
            'Ada',
            'I want another mug',
        );

        $this->assertStringContainsString('ORD-1001', $block);
        $this->assertStringContainsString('Catalog size: 1', $block);
        $this->assertStringContainsString('business OS', $block);
        $this->assertStringContainsString('I want another mug', $block);
        $this->assertStringContainsString('remember_customer', $block);
    }

    public function test_system_prompt_positions_ai_as_conversation_os(): void
    {
        $company = Company::create([
            'name' => 'Fluent Co',
            'email' => 'fluent@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'ai_tone' => 'warm and confident',
            'agent_commerce_enabled' => true,
        ]);

        $prompt = app(SystemPromptBuilder::class)->build(
            $company->fresh(),
            [['question' => 'Do you deliver?', 'answer' => 'Yes within Nairobi.']],
            null,
            'Do you deliver to Westlands?',
        );

        $this->assertStringContainsString('primary AI employee and conversation OS', $prompt);
        $this->assertStringContainsString('NOT a rigid menu bot', $prompt);
        $this->assertStringContainsString('Do you deliver?', $prompt);
    }

    public function test_agent_commerce_defaults_on(): void
    {
        $this->assertTrue((bool) config('agent.default_agent_commerce_enabled'));
        $this->assertTrue((bool) config('agent.company.reasoning_enabled'));
    }
}
