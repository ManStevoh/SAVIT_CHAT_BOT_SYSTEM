<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Services\Agent\AgentToolRegistry;
use App\Services\Agent\Company\CustomerIntentChainService;
use App\Services\Agent\Company\ReasoningEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AgentAutonomousDoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_catalog_lists_registered_tools_dynamically(): void
    {
        $company = Company::create([
            'name' => 'Dynamic Co',
            'email' => 'dynamic@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'agent_commerce_enabled' => true,
        ]);

        $catalog = app(AgentToolRegistry::class)->capabilityCatalogForPrompt($company->fresh(['settings']));

        $this->assertStringContainsString('Live action capabilities', $catalog);
        $this->assertStringContainsString('send_order_invoice', $catalog);
        $this->assertStringContainsString('search_orders', $catalog);
        $this->assertStringContainsString('process_order_message', $catalog);
        $this->assertStringContainsString('transfer_to_human', $catalog);
        $this->assertStringContainsString('choose by customer intent', $catalog);
    }

    public function test_reasoning_prompt_block_directs_tool_execution_when_action_required(): void
    {
        $service = app(ReasoningEngineService::class);
        $method = new ReflectionMethod(ReasoningEngineService::class, 'formatForChiefAgent');
        $method->setAccessible(true);

        $block = $method->invoke($service, [
            'understanding' => 'Customer wants proof of purchase sent now',
            'action_required' => true,
            'action_kind' => 'send_document',
            'chosen_plan' => 'Fulfill document delivery via available tools',
        ], ['label' => 'neutral', 'score' => 0]);

        $this->assertStringContainsString('Action required: yes', $block);
        $this->assertStringContainsString('Action kind: send_document', $block);
        $this->assertStringContainsString('execute matching tool(s) this turn', $block);
    }

    public function test_intent_chain_prefers_action_kind_from_ai_trace(): void
    {
        $company = Company::create([
            'name' => 'Intent Co',
            'email' => 'intent@test.local',
            'status' => 'active',
        ]);

        app(CustomerIntentChainService::class)->advanceFromReasoning($company, '254700999888', [
            'understanding' => 'They need their payment slip',
            'action_kind' => 'send_document',
            'chosen_plan' => 'Send document',
        ]);

        $chain = app(CustomerIntentChainService::class)->getChain((int) $company->id, '254700999888');
        $this->assertNotNull($chain);
        $this->assertSame('support', $chain['primary_intent']);
        $this->assertSame('post_purchase', $chain['stage']);
    }

    public function test_operating_rules_are_intent_driven_not_phrase_maps(): void
    {
        $company = Company::create([
            'name' => 'Rules Co',
            'email' => 'rules@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create(['company_id' => $company->id]);

        $text = app(\App\Services\Agent\AgentCustomerIntelligenceContext::class)->build(
            $company->fresh(['settings']),
            '254700111222',
            'Buyer',
            'can you sort the bill for me?',
        );

        $this->assertStringContainsString('Infer intent from meaning', $text);
        $this->assertStringContainsString('do-actions', $text);
        $this->assertStringNotContainsString('Invoice / receipt / bill requests: use send_order_invoice', $text);
    }

    public function test_keyword_handoff_disabled_when_agent_owns_intent(): void
    {
        $chat = new \App\Models\Chat(['conversation_step' => null]);
        $job = new \App\Jobs\ProcessIncomingWhatsAppMessage(
            companyId: 1,
            chatId: 1,
            customerPhone: '254700111222',
            phoneNumberId: 'pn',
            messageText: 'I need support with my invoice please',
        );

        $method = new \ReflectionMethod(\App\Jobs\ProcessIncomingWhatsAppMessage::class, 'wantsHumanEscalation');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($job, $chat, true), 'legacy bots still keyword-match');
        $this->assertFalse($method->invoke($job, $chat, false), 'agent commerce must not keyword-short-circuit');

        $menuJob = new \App\Jobs\ProcessIncomingWhatsAppMessage(
            companyId: 1,
            chatId: 1,
            customerPhone: '254700111222',
            phoneNumberId: 'pn',
            messageText: '3',
        );
        $this->assertTrue($method->invoke($menuJob, $chat, false), 'quick-menu 3 still escalates');
    }
}
