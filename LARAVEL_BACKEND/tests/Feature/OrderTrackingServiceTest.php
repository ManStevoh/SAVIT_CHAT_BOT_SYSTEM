<?php

namespace Tests\Feature;

use App\DTOs\ConversationState;
use App\Enums\CheckoutStep;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Services\AI\ReadOnlyContextBuilder;
use App\Services\Conversation\ConversationGreetingService;

use App\Services\Domain\OrderTrackingService;
use App\Services\Workflow\WorkflowEngine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedEnvironment(): array
    {
        $company = Company::create([
            'name' => 'Wafulla Electronics',
            'email' => 'wafulla@test.local',
            'status' => 'active',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Black Sneakers',
            'price' => 350.00,
            'stock' => 10,
            'status' => 'active',
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700111222',
            'customer_name' => 'Ken',
            'status' => 'active',
        ]);

        return [$company, $chat, $product];
    }

    public function test_order_tracker_returns_no_orders_found_when_customer_has_zero_orders(): void
    {
        [$company, $chat] = $this->seedEnvironment();

        $trackingService = app(OrderTrackingService::class);
        $orders = $trackingService->getRecentOrders($company, $chat->customer_phone, $chat->id);

        $this->assertCount(0, $orders);

        $engine = app(WorkflowEngine::class);
        $state = new ConversationState(
            chatId: $chat->id,
            companyId: $company->id,
            customerPhone: '254700111222',
            customerName: 'Ken',
            step: CheckoutStep::IDLE
        );

        $intent = new \App\DTOs\IntentResult(
            intent: \App\Enums\CommerceIntent::ASK_ORDER_STATUS,
            confidence: 0.95,
            messageText: '2'
        );

        $result = $engine->handle($state, $intent, $company);
        $this->assertStringContainsString('Order Tracker', (string) $result->customerReply);
        $this->assertStringContainsString("couldn't find any recent orders", (string) $result->customerReply);
    }

    public function test_order_tracker_returns_single_order_card_when_customer_has_one_order(): void
    {
        [$company, $chat, $product] = $this->seedEnvironment();

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => '160',
            'customer_name' => 'Ken',
            'customer_phone' => '254700111222',
            'total' => 350.00,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'mpesa',
        ]);

        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => 'Black Sneakers',
            'quantity' => 1,
            'price' => 350.00,
            'line_subtotal' => 350.00,
        ]);

        $engine = app(WorkflowEngine::class);
        $state = new ConversationState(
            chatId: $chat->id,
            companyId: $company->id,
            customerPhone: '254700111222',
            customerName: 'Ken',
            step: CheckoutStep::IDLE
        );

        $intent = new \App\DTOs\IntentResult(
            intent: \App\Enums\CommerceIntent::ASK_ORDER_STATUS,
            confidence: 0.95,
            messageText: 'track order'
        );

        $result = $engine->handle($state, $intent, $company);
        $this->assertStringContainsString('Order #160', (string) $result->customerReply);
        $this->assertStringContainsString('Order Confirmed & Preparing', (string) $result->customerReply);
        $this->assertStringContainsString('Black Sneakers', (string) $result->customerReply);
    }

    public function test_order_tracker_returns_order_list_when_customer_has_multiple_orders(): void
    {
        [$company, $chat, $product] = $this->seedEnvironment();

        Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => '160',
            'customer_name' => 'Ken',
            'customer_phone' => '254700111222',
            'total' => 350.00,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => '155',
            'customer_name' => 'Ken',
            'customer_phone' => '254700111222',
            'total' => 100.00,
            'status' => 'delivered',
            'payment_status' => 'paid',
        ]);

        $engine = app(WorkflowEngine::class);
        $state = new ConversationState(
            chatId: $chat->id,
            companyId: $company->id,
            customerPhone: '254700111222',
            customerName: 'Ken',
            step: CheckoutStep::IDLE
        );

        $intent = new \App\DTOs\IntentResult(
            intent: \App\Enums\CommerceIntent::ASK_ORDER_STATUS,
            confidence: 0.95,
            messageText: 'my order'
        );

        $result = $engine->handle($state, $intent, $company);
        $this->assertStringContainsString('Your Recent Orders', (string) $result->customerReply);
        $this->assertStringContainsString('Order #160', (string) $result->customerReply);
        $this->assertStringContainsString('Order #155', (string) $result->customerReply);
    }

    public function test_order_tracker_looks_up_specific_order_number_160(): void
    {
        [$company, $chat, $product] = $this->seedEnvironment();

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => '160',
            'customer_name' => 'Ken',
            'customer_phone' => '254700111222',
            'total' => 350.00,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $engine = app(WorkflowEngine::class);
        $state = new ConversationState(
            chatId: $chat->id,
            companyId: $company->id,
            customerPhone: '254700111222',
            customerName: 'Ken',
            step: CheckoutStep::IDLE
        );

        $intent = new \App\DTOs\IntentResult(
            intent: \App\Enums\CommerceIntent::ASK_ORDER_STATUS,
            confidence: 0.95,
            messageText: '#160'
        );

        $result = $engine->handle($state, $intent, $company);
        $this->assertStringContainsString('Order #160', (string) $result->customerReply);
    }

    public function test_read_only_context_builder_injects_verified_orders_into_llm_facts(): void
    {
        [$company, $chat, $product] = $this->seedEnvironment();

        Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => '160',
            'customer_name' => 'Ken',
            'customer_phone' => '254700111222',
            'total' => 350.00,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $contextBuilder = app(ReadOnlyContextBuilder::class);
        $state = new ConversationState(
            chatId: $chat->id,
            companyId: $company->id,
            customerPhone: '254700111222',
            customerName: 'Ken',
            step: CheckoutStep::IDLE
        );

        $facts = $contextBuilder->build($company, $state);

        $this->assertArrayHasKey('verified_orders', $facts);
        $this->assertCount(1, $facts['verified_orders']);
        $this->assertEquals('160', $facts['verified_orders'][0]['order_number']);
        $this->assertEquals('confirmed', $facts['verified_orders'][0]['status']);
    }
}
