<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Tools\ProcessOrderMessageTool;
use App\Services\OrderFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutShorthandOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: Chat, 2: Product}
     */
    private function seedCatalogChat(): array
    {
        $company = Company::create([
            'name' => 'Wafulla Stores',
            'email' => 'wafulla@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'orders_collect_payment_enabled' => true,
            'order_payment_manual_enabled' => true,
            'order_payment_manual_instructions' => "Pay via till 12345\nBusiness: Wafulla Stores",
            'order_payment_mpesa_enabled' => false,
            'order_payment_stripe_enabled' => false,
            'order_payment_paystack_enabled' => false,
        ]);
        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Headphones',
            'price' => 20,
            'stock' => 100,
            'status' => 'active',
            'product_type' => 'digital',
            'fulfillment_type' => 'link',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'access_url' => 'https://example.com/headphones',
        ]);
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700111222',
            'customer_name' => 'Buyer',
            'status' => 'active',
            'last_message' => 'I want headphones',
            'last_message_at' => now(),
        ]);

        return [$company, $chat, $product];
    }

    public function test_order_flow_confirms_on_okay_pay_phrasing(): void
    {
        [$company, $chat, $product] = $this->seedCatalogChat();
        $draft = [
            'items' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'quantity' => 10,
                'fulfillment_data' => $product->fulfillmentSnapshot(),
            ]],
        ];
        $chat->update([
            'conversation_step' => OrderFlowService::STEP_CONFIRM,
            'order_draft' => $draft,
        ]);

        $reply = app(OrderFlowService::class)->processMessage(
            $chat->fresh(),
            $company->fresh(['settings']),
            'okay, pay via 12345',
            'Buyer',
            '254700111222',
        );

        $this->assertNotNull($reply);
        $this->assertStringContainsString('ORD-', (string) $reply);
        $this->assertTrue(Order::where('company_id', $company->id)->exists());
    }

    public function test_order_flow_accepts_10x_as_quantity_when_product_pending(): void
    {
        [$company, $chat, $product] = $this->seedCatalogChat();
        $chat->update([
            'conversation_step' => OrderFlowService::STEP_PRODUCT_QTY,
            'order_draft' => [
                'items' => [],
                'pending_product_id' => $product->id,
            ],
        ]);

        $reply = app(OrderFlowService::class)->processMessage(
            $chat->fresh(),
            $company->fresh(['settings']),
            '10x',
            'Buyer',
            '254700111222',
        );

        $this->assertNotNull($reply);
        $this->assertStringContainsString('Headphones', (string) $reply);
        $chat->refresh();
        $this->assertSame(OrderFlowService::STEP_PRODUCT, $chat->conversation_step);
        $this->assertSame(10, (int) ($chat->order_draft['items'][0]['quantity'] ?? 0));
    }

    public function test_process_order_tool_composes_10x_and_places_order_on_pay_intent(): void
    {
        [$company, $chat, $product] = $this->seedCatalogChat();

        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Do you have Headphones?',
            'sender' => 'customer',
            'status' => 'sent',
        ]);
        Message::create([
            'chat_id' => $chat->id,
            'content' => "Yes — Headphones are 20.00 each.\nPlease reply with '10 x Headphones' so we can proceed.",
            'sender' => 'bot',
            'status' => 'sent',
        ]);
        Message::create([
            'chat_id' => $chat->id,
            'content' => '10x',
            'sender' => 'customer',
            'status' => 'sent',
        ]);

        $context = new AgentToolContext(
            company: $company->fresh(['settings']),
            chat: $chat->fresh(),
            customerPhone: '254700111222',
            customerName: 'Buyer',
            incomingMessage: 'okay, pay via 12345',
        );

        $result = app(ProcessOrderMessageTool::class)->execute($context, [
            'message' => 'okay, pay via 12345',
        ]);

        $this->assertTrue($result['has_reply'] ?? false, json_encode($result));
        $this->assertNotEmpty($result['order_flow_reply'] ?? null);
        $this->assertStringContainsString('ORD-', (string) $result['order_flow_reply']);
        $this->assertTrue(
            Order::where('company_id', $company->id)->where('customer_phone', '254700111222')->exists()
        );
        $order = Order::where('company_id', $company->id)->first();
        $this->assertEquals(200.0, (float) $order->total);
    }

    public function test_process_order_tool_expands_bare_10x_using_thread_product(): void
    {
        [$company, $chat] = $this->seedCatalogChat();

        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Looking for Headphones please',
            'sender' => 'customer',
            'status' => 'sent',
        ]);
        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Headphones are available. Reply with 10 x Headphones to order.',
            'sender' => 'bot',
            'status' => 'sent',
        ]);

        $context = new AgentToolContext(
            company: $company->fresh(['settings']),
            chat: $chat->fresh(),
            customerPhone: '254700111222',
            customerName: 'Buyer',
            incomingMessage: '10x',
        );

        $result = app(ProcessOrderMessageTool::class)->execute($context, [
            'message' => '10x',
        ]);

        $this->assertTrue($result['has_reply'] ?? false, json_encode($result));
        $this->assertStringContainsString('Headphones', (string) $result['order_flow_reply']);
        $chat->refresh();
        $this->assertNotEmpty($chat->order_draft['items'] ?? []);
        $this->assertSame(10, (int) ($chat->order_draft['items'][0]['quantity'] ?? 0));
    }

    public function test_agent_context_forbids_magic_checkout_phrases(): void
    {
        [$company] = $this->seedCatalogChat();
        $text = app(\App\Services\Agent\AgentCustomerIntelligenceContext::class)->build(
            $company->fresh(['settings']),
            '254700111222',
            'Buyer',
            'okay pay',
        );

        $this->assertStringContainsString('Never ask the customer to type a magic phrase', $text);
    }
}
