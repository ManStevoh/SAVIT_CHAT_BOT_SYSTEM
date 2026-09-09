<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CustomerMemory;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Services\Agent\AgentProactiveMessageService;
use App\Services\AI\AiGateway;
use App\Services\AI\OpenAiChatResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AgentProactiveMessageGroundingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['deploy.secret' => 'test-secret-12345']);
        config(['deploy.agent_key' => 'test-agent-key-xyz']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createCompanyAndOrder(): array
    {
        $company = Company::create([
            'name' => 'Jostina Bookshop',
            'email' => 'jostina@test.local',
            'status' => 'active',
        ]);

        CompanySetting::create([
            'company_id' => $company->id,
            'agent_commerce_enabled' => true,
            'agent_proactive_enabled' => true,
            'display_currency' => 'KES',
            'currency_symbol' => 'KSh',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'My Web',
            'price' => 700.00,
            'stock' => 10,
            'status' => 'active',
            'description' => 'A story of a tigress',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-TEST-001',
            'customer_name' => 'Wekesa',
            'customer_phone' => '254728210962',
            'total' => 700.00,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => 'My Web',
            'quantity' => 1,
            'price' => 700.00,
        ]);

        return [$company, $product, $order];
    }

    public function test_payment_received_message_includes_order_items_and_catalog_in_prompt(): void
    {
        [$company, $product, $order] = $this->createCompanyAndOrder();

        $capturedMessages = [];

        $aiGateway = Mockery::mock(AiGateway::class);
        $aiGateway->shouldReceive('chatCompletion')
            ->once()
            ->andReturnUsing(function ($messages) use (&$capturedMessages) {
                $capturedMessages = $messages;

                return new OpenAiChatResult(
                    success: true,
                    content: 'Hi Wekesa! Thank you for ordering My Web! Your payment has been received.',
                    model: 'gpt-4o-mini',
                );
            });

        $this->app->instance(AiGateway::class, $aiGateway);

        $service = app(AgentProactiveMessageService::class);
        $result = $service->paymentReceivedMessage($order);

        $this->assertNotNull($result);
        $this->assertStringContainsString('My Web', $result);

        // Verify prompt captured order items and catalog
        $systemPrompt = $capturedMessages[0]['content'];
        $userPrompt = $capturedMessages[1]['content'];

        $this->assertStringContainsString('Active store catalog for Jostina Bookshop', $systemPrompt);
        $this->assertStringContainsString('My Web (KSh 700.00)', $systemPrompt);
        $this->assertStringContainsString('CRITICAL GROUNDING RULES', $systemPrompt);

        $this->assertStringContainsString('items_purchased', $userPrompt);
        $this->assertStringContainsString('My Web', $userPrompt);
    }

    public function test_proactive_message_rejects_hallucinated_headphones_and_falls_back(): void
    {
        [$company, $product, $order] = $this->createCompanyAndOrder();

        // Customer memory mentions headphones from an old conversation
        CustomerMemory::create([
            'company_id' => $company->id,
            'customer_phone' => '254728210962',
            'memory_key' => 'interested_in',
            'memory_value' => 'red headphones',
            'category' => 'preference',
        ]);

        $aiGateway = Mockery::mock(AiGateway::class);
        $aiGateway->shouldReceive('chatCompletion')
            ->once()
            ->andReturn(new OpenAiChatResult(
                success: true,
                content: 'Thank you for your recent order (ORD-TEST-001) of headphones! We also have red headphones! 🎧',
                model: 'gpt-4o-mini',
            ));

        $this->app->instance(AiGateway::class, $aiGateway);

        $service = app(AgentProactiveMessageService::class);
        $result = $service->paymentReceivedMessage($order);

        // Validation must reject the hallucinated headphones message and fall back to safe confirmation
        $this->assertNotNull($result);
        $this->assertStringNotContainsString('headphones', strtolower($result));
        $this->assertStringContainsString('Payment received. Your order #ORD-TEST-001 is confirmed.', $result);
    }

    public function test_agent_store_gateway_lists_and_clears_customer_memories(): void
    {
        [$company] = $this->createCompanyAndOrder();

        CustomerMemory::create([
            'company_id' => $company->id,
            'customer_phone' => '254728210962',
            'memory_key' => 'interested_in',
            'memory_value' => 'red headphones',
            'category' => 'preference',
        ]);

        // List memories
        $response = $this->withHeaders(['X-Deploy-Agent-Key' => 'test-agent-key-xyz'])
            ->postJson('/api/agent/store', [
                'action' => 'list_memories',
                'store' => $company->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('count', 1);
        $this->assertEquals('red headphones', $response->json('memories.0.memory_value'));

        // Clear memories
        $clearResponse = $this->withHeaders(['X-Deploy-Agent-Key' => 'test-agent-key-xyz'])
            ->postJson('/api/agent/store', [
                'action' => 'clear_memories',
                'store' => $company->id,
                'phone' => '254728210962',
            ]);

        $clearResponse->assertOk();
        $clearResponse->assertJsonPath('deleted_count', 1);

        $this->assertDatabaseMissing('customer_memories', [
            'company_id' => $company->id,
            'customer_phone' => '254728210962',
        ]);
    }

    public function test_whatsapp_catalog_and_cart_render_with_company_currency_symbol_ksh(): void
    {
        [$company, $product] = $this->createCompanyAndOrder();

        // 1. Catalog prompt
        $catalogOutput = \App\Services\Workflow\ResponseSpecRenderer::renderCatalogPrompt($company);
        $this->assertStringContainsString('KSh 700.00', $catalogOutput);
        $this->assertStringNotContainsString('$700.00', $catalogOutput);

        // 2. Order prompt
        $orderOutput = \App\Services\Workflow\ResponseSpecRenderer::renderOrderPrompt($company);
        $this->assertStringContainsString('KSh 700.00', $orderOutput);
        $this->assertStringNotContainsString('$700.00', $orderOutput);

        // 3. Cart Summary
        $renderer = new \App\Services\Workflow\ResponseSpecRenderer();
        $state = new \App\DTOs\ConversationState(
            chatId: 1,
            companyId: $company->id,
            customerPhone: '254728210962',
            customerName: 'Wekesa',
            step: \App\Enums\CheckoutStep::BUILDING_CART,
            cartItems: [[
                'product_id' => $product->id,
                'name' => 'My Web',
                'price' => 700.00,
                'quantity' => 1,
            ]]
        );

        $cartSummary = $renderer->render(\App\Enums\ResponseSpec::CART_SUMMARY, $state, $company, [
            'added_product_name' => 'My Web',
            'added_product_qty' => 1,
        ]);

        $this->assertStringContainsString('1 x KSh 700.00', $cartSummary);
        $this->assertStringContainsString('*Total:* KSh 700.00', $cartSummary);
        $this->assertStringNotContainsString('$', $cartSummary);

        // 4. Order Confirmation Review
        $review = $renderer->render(\App\Enums\ResponseSpec::PROMPT_ORDER_CONFIRMATION, $state, $company);
        $this->assertStringContainsString('1 x KSh 700.00', $review);
        $this->assertStringContainsString('*Total:* KSh 700.00', $review);
        $this->assertStringNotContainsString('$', $review);
    }
}
