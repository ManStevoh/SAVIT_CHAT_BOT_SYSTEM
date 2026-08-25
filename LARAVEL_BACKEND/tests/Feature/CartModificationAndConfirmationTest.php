<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Agent\CheckoutMessageComposer;
use App\Services\Agent\CommerceAgentOrchestrator;
use App\Services\OrderFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class CartModificationAndConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_message_composer_extracts_swap_and_removal(): void
    {
        $company = Company::create(['name' => 'Test Business', 'email' => 'test' . rand(1000, 9999) . '@example.com', 'slug' => 'test-biz-' . rand(1000, 9999)]);
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254712345678',
            'customer_name' => 'Ken',
            'conversation_step' => OrderFlowService::STEP_PRODUCT,
            'order_draft' => [
                'items' => [
                    ['name' => 'CS Book', 'quantity' => 1, 'price' => 100],
                ],
            ],
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Earphones',
            'price' => 150,
            'status' => 'active',
        ]);

        $variantRed = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Red',
            'label' => 'Red',
            'price' => 150,
            'status' => 'active',
        ]);

        $composer = app(CheckoutMessageComposer::class);
        $context = new \App\Services\Agent\AgentToolContext(
            company: $company,
            chat: $chat,
            customerPhone: '254712345678',
            customerName: 'Ken',
            incomingMessage: 'remove the cs book I want to order the earphones instead'
        );

        $candidates = $composer->candidateMessages($context, 'remove the cs book I want to order the earphones instead');

        $this->assertContains('remove CS Book', $candidates);
        $this->assertContains('1 x Earphones', $candidates);
    }

    public function test_orchestrator_forces_order_tool_on_affirmations_and_variant_selections(): void
    {
        $company = Company::create(['name' => 'Test Business', 'email' => 'test' . rand(1000, 9999) . '@example.com', 'slug' => 'test-biz-' . rand(1000, 9999)]);
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254712345678',
            'customer_name' => 'Ken',
            'conversation_step' => OrderFlowService::STEP_PRODUCT,
        ]);

        $chat->messages()->create([
            'sender' => 'bot',
            'content' => 'Would you like to proceed with removing the CS Book and ordering the earphones instead? If so, please let me know which color you\'d like: Red Earphones or White Earphones',
        ]);

        $orch = app(CommerceAgentOrchestrator::class);
        $force = new ReflectionMethod(CommerceAgentOrchestrator::class, 'shouldForceDoActionTool');
        $force->setAccessible(true);

        // When bot asked a question and customer replies "yes give me the red ones", it MUST force process_order_message
        $this->assertTrue($force->invoke($orch, false, 'inquiry', [], false, $chat, 'yes give me the red ones'));
        $this->assertTrue($force->invoke($orch, false, 'inquiry', [], false, $chat, 'yes'));
    }

    public function test_order_intent_with_pronouns_resolves_product_from_bot_context(): void
    {
        $company = Company::create(['name' => 'Test Business', 'email' => 'test' . rand(1000, 9999) . '@example.com', 'slug' => 'test-biz-' . rand(1000, 9999)]);
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254712345678',
            'customer_name' => 'Ken',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Earphones',
            'price' => 150,
            'status' => 'active',
        ]);

        $chat->messages()->create([
            'sender' => 'bot',
            'content' => 'Hi Ken! Yes, we do have red earphones available. Price: $150.00. Would you like to place an order for them? Just let me know!',
        ]);

        $composer = app(CheckoutMessageComposer::class);
        $context = new \App\Services\Agent\AgentToolContext(
            company: $company,
            chat: $chat,
            customerPhone: '254712345678',
            customerName: 'Ken',
            incomingMessage: 'yes, I want to order them'
        );

        $candidates = $composer->candidateMessages($context, 'yes, I want to order them');
        $this->assertContains('1 x Earphones', $candidates);
    }
}
