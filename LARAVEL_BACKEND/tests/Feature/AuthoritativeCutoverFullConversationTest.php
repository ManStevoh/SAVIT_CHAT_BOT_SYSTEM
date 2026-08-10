<?php

namespace Tests\Feature;

use App\DTOs\InboundEnvelope;
use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Product;
use App\Services\Channels\WhatsAppChannelAdapter;
use App\Services\Domain\CartDomainService;
use App\Services\Domain\FulfillmentDomainService;
use App\Services\Domain\OrderDomainService;
use App\Services\WhatsAppMessageSenderService;
use App\Services\Workflow\ConversationalOSPipeline;
use App\Services\Workflow\DomainServiceDispatcher;
use App\Services\Workflow\ResponseSpecRenderer;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthoritativeCutoverFullConversationTest extends TestCase
{
    use RefreshDatabase;

    private function seedFullEnvironment(): array
    {
        $company = Company::create([
            'name' => 'Ken Businesses',
            'email' => 'kenbusinesses@test.local',
            'status' => 'active',
        ]);

        CompanySetting::create([
            'company_id' => $company->id,
            'orders_collect_payment_enabled' => true,
            'order_payment_manual_enabled' => true,
            'order_payment_mpesa_enabled' => true,
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Red Headphones',
            'price' => 150.00,
            'stock' => 50,
            'status' => 'active',
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '0712345678',
            'customer_name' => 'Ken',
            'status' => 'active',
        ]);

        return [$company, $chat, $product];
    }

    public function test_full_authoritative_cutover_multi_turn_checkout_conversation(): void
    {
        [$company, $chat, $product] = $this->seedFullEnvironment();

        $outboundReplyCount = 0;
        $outboundReplies = [];

        $senderMock = $this->createMock(WhatsAppMessageSenderService::class);
        $senderMock->expects($this->exactly(8))
            ->method('sendMessage')
            ->willReturnCallback(function ($comp, $to, $msg) use (&$outboundReplyCount, &$outboundReplies) {
                $outboundReplyCount++;
                $outboundReplies[] = $msg;
                return ['success' => true];
            });

        $channelAdapter = new WhatsAppChannelAdapter($senderMock);

        $domainDispatcher = new DomainServiceDispatcher(
            new CartDomainService(),
            new OrderDomainService(),
            new FulfillmentDomainService()
        );

        $pipeline = new ConversationalOSPipeline(
            new \App\Services\Conversation\ConversationStateHydrator(),
            app(\App\Services\AI\UnifiedIntentClassifierService::class),
            new WorkflowEngine($domainDispatcher, new ResponseSpecRenderer())
        );

        $turns = [
            1 => 'prices',
            2 => 'i want red headphones',
            3 => 'yes',
            4 => 'delivery',
            5 => 'Mombasa Nyali near X',
            6 => 'yes confirm',
            7 => 'mpesa',
            8 => '0712345678',
        ];

        foreach ($turns as $turnNumber => $userMessage) {
            $envelope = new InboundEnvelope(
                channelType: 'whatsapp',
                externalSenderId: '0712345678',
                companyId: $company->id,
                messageText: $userMessage,
                senderName: 'Ken'
            );

            $pipeline->processTurn($company, $chat, $envelope, $channelAdapter);
        }

        // Assert 1: Exactly 8 inbound messages received -> Exactly 8 outbound replies (exactly one per message)
        $this->assertEquals(8, $outboundReplyCount);
        $this->assertCount(8, $outboundReplies);

        // Assert 2: No duplicate cart items
        $chat->refresh();
        $cartItems = $chat->order_draft['items'] ?? [];
        $this->assertCount(1, $cartItems);
        $this->assertEquals('Red Headphones', $cartItems[0]['name']);
        $this->assertEquals(1, $cartItems[0]['quantity']);

        // Assert 3: Correct state progression
        $this->assertEquals('awaiting_payment', $chat->conversation_step);
        $this->assertEquals('Mombasa Nyali near X', $chat->order_draft['delivery_address']);

        // Assert 4: Order created exactly once
        $orders = Order::where('company_id', $company->id)->get();
        $this->assertCount(1, $orders);
        $order = $orders->first();
        $this->assertEquals(150.00, (float) $order->total_amount);
        $this->assertEquals('Mombasa Nyali near X', $order->delivery_address);

        // Assert 5: Payment request / STK notice triggered exactly once
        $lastReply = end($outboundReplies);
        $this->assertStringContainsString('STK Push prompt sent to 0712345678', $lastReply);
    }
}
