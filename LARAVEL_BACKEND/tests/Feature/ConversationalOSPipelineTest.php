<?php

namespace Tests\Feature;

use App\DTOs\InboundEnvelope;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Product;
use App\Services\Channels\WhatsAppChannelAdapter;

use App\Services\OrderFlowService;

use App\Services\WhatsAppMessageSenderService;

use App\Services\Workflow\ConversationalOSPipeline;
use App\Services\Workflow\DomainServiceDispatcher;
use App\Services\Workflow\ResponseSpecRenderer;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationalOSPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function seedFullEnvironment(): array
    {
        $company = Company::create([
            'name' => 'Ken Store',
            'email' => 'kenstore@test.local',
            'status' => 'active',
        ]);

        CompanySetting::create([
            'company_id' => $company->id,
            'orders_collect_payment_enabled' => true,
            'order_payment_manual_enabled' => true,
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
            'customer_phone' => '254712345678',
            'customer_name' => 'Ken',
            'status' => 'active',
        ]);

        return [$company, $chat, $product];
    }

    public function test_5_layer_pipeline_executes_add_to_cart_and_checkout_flow(): void
    {
        [$company, $chat, $product] = $this->seedFullEnvironment();

        $senderMock = $this->createMock(WhatsAppMessageSenderService::class);
        $senderMock->method('sendMessage')->willReturn(['success' => true]);

        $channelAdapter = new WhatsAppChannelAdapter($senderMock);

        $pipeline = new ConversationalOSPipeline(
            new \App\Services\Conversation\ConversationStateHydrator(),
            app(\App\Services\AI\UnifiedIntentClassifierService::class),
            new WorkflowEngine(
                new DomainServiceDispatcher(app(OrderFlowService::class)),
                new ResponseSpecRenderer()
            )
        );

        // Turn 1: Customer adds item to cart
        $envelope1 = new InboundEnvelope(
            channelType: 'whatsapp',
            externalSenderId: '254712345678',
            companyId: $company->id,
            messageText: 'add 1 Red Headphones'
        );

        $res1 = $pipeline->processTurn($company, $chat, $envelope1, $channelAdapter);

        $this->assertEquals('product', $chat->fresh()->conversation_step);
        $this->assertCount(1, $chat->fresh()->order_draft['items']);
        $this->assertEquals('Red Headphones', $chat->fresh()->order_draft['items'][0]['name']);

        // Turn 2: Customer starts checkout
        $envelope2 = new InboundEnvelope(
            channelType: 'whatsapp',
            externalSenderId: '254712345678',
            companyId: $company->id,
            messageText: 'checkout'
        );

        $res2 = $pipeline->processTurn($company, $chat, $envelope2, $channelAdapter);

        $this->assertEquals('address', $chat->fresh()->conversation_step);

        // Turn 3: Customer provides delivery address
        $envelope3 = new InboundEnvelope(
            channelType: 'whatsapp',
            externalSenderId: '254712345678',
            companyId: $company->id,
            messageText: 'Kenyatta Avenue, Nairobi'
        );

        $res3 = $pipeline->processTurn($company, $chat, $envelope3, $channelAdapter);

        $this->assertEquals('confirm', $chat->fresh()->conversation_step);
        $this->assertEquals('Kenyatta Avenue, Nairobi', $chat->fresh()->order_draft['delivery_address']);

        // Turn 4: Customer confirms order placement
        $envelope4 = new InboundEnvelope(
            channelType: 'whatsapp',
            externalSenderId: '254712345678',
            companyId: $company->id,
            messageText: 'confirm'
        );

        $res4 = $pipeline->processTurn($company, $chat, $envelope4, $channelAdapter);

        $this->assertEquals('payment_method', $chat->fresh()->conversation_step);
        $this->assertTrue(Order::where('company_id', $company->id)->exists());
    }
}
