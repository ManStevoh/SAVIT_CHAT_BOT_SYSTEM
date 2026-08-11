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
        $channelAdapter = new WhatsAppChannelAdapter($senderMock);

        $pipeline = app(ConversationalOSPipeline::class);

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

    public function test_numeric_catalog_product_selection(): void
    {
        $company = Company::create([
            'name' => 'Multi Item Store',
            'email' => 'multi@test.local',
            'status' => 'active',
        ]);

        CompanySetting::create([
            'company_id' => $company->id,
            'orders_collect_payment_enabled' => true,
        ]);

        // Create 5 products (alphabetical order: Black Sneakers, CS Book, Earphones, Headphones, Shoerack)
        $p1 = Product::create(['company_id' => $company->id, 'name' => 'Black Sneakers', 'price' => 350.0, 'stock' => 10, 'status' => 'active']);
        $p2 = Product::create(['company_id' => $company->id, 'name' => 'CS Book', 'price' => 100.0, 'stock' => 10, 'status' => 'active']);
        $p3 = Product::create(['company_id' => $company->id, 'name' => 'Earphones', 'price' => 100.0, 'stock' => 10, 'status' => 'active']);
        $p4 = Product::create(['company_id' => $company->id, 'name' => 'Headphones', 'price' => 200.0, 'stock' => 10, 'status' => 'active']);
        $p5 = Product::create(['company_id' => $company->id, 'name' => 'Shoerack', 'price' => 600.0, 'stock' => 10, 'status' => 'active']);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254799887766',
            'customer_name' => 'Alice',
            'status' => 'active',
        ]);

        $senderMock = $this->createMock(WhatsAppMessageSenderService::class);
        $channelAdapter = new WhatsAppChannelAdapter($senderMock);

        $pipeline = app(ConversationalOSPipeline::class);

        // Turn 1: Customer asks for prices / catalog
        $envCatalog = new InboundEnvelope('whatsapp', '254799887766', $company->id, 'prices');
        $resCatalog = $pipeline->processTurn($company, $chat, $envCatalog, $channelAdapter);
        $this->assertStringContainsString('Black Sneakers', $resCatalog->customerReply);
        $this->assertStringContainsString('Shoerack', $resCatalog->customerReply);

        // Turn 2: Customer replies "1" -> should add Black Sneakers (item #1), NOT re-dump catalog
        $env1 = new InboundEnvelope('whatsapp', '254799887766', $company->id, '1');
        $res1 = $pipeline->processTurn($company, $chat, $env1, $channelAdapter);
        $this->assertStringContainsString('Black Sneakers', $res1->customerReply);
        $this->assertStringNotContainsString('Here\'s our product catalog:', $res1->customerReply);

        // Turn 3: Customer replies "5" -> should add Shoerack (item #5)
        $env5 = new InboundEnvelope('whatsapp', '254799887766', $company->id, '5');
        $res5 = $pipeline->processTurn($company, $chat, $env5, $channelAdapter);
        $this->assertStringContainsString('Shoerack', $res5->customerReply);

        // Turn 4: Customer replies "3" -> should add Earphones (item #3), NOT connect to human agent
        $env3 = new InboundEnvelope('whatsapp', '254799887766', $company->id, '3');
        $res3 = $pipeline->processTurn($company, $chat, $env3, $channelAdapter);
        $this->assertStringContainsString('Earphones', $res3->customerReply);
        $this->assertStringNotContainsString('Connecting you with a support representative', $res3->customerReply);
    }

    public function test_cart_item_quantity_reduction_and_removal(): void
    {
        $company = Company::create([
            'name' => 'Sneaker Store',
            'email' => 'sneakers@test.local',
            'status' => 'active',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Black Sneakers',
            'price' => 350.0,
            'stock' => 10,
            'status' => 'active',
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254711223344',
            'customer_name' => 'Bob',
            'status' => 'active',
            'conversation_step' => 'product',
            'order_draft' => [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'name' => 'Black Sneakers',
                        'price' => 350.0,
                        'quantity' => 2,
                    ],
                ],
            ],
        ]);

        $senderMock = $this->createMock(WhatsAppMessageSenderService::class);
        $channelAdapter = new WhatsAppChannelAdapter($senderMock);

        $pipeline = app(ConversationalOSPipeline::class);

        // Turn 1: Customer asks to "remove one sneaker i just want a pair"
        $env = new InboundEnvelope('whatsapp', '254711223344', $company->id, 'remove one sneaker i just want a pair');
        $res = $pipeline->processTurn($company, $chat, $env, $channelAdapter);

        // Assert quantity was reduced from 2 to 1 instead of adding another item
        $items = $chat->fresh()->order_draft['items'];
        $this->assertCount(1, $items);
        $this->assertEquals(1, $items[0]['quantity']);
        $this->assertStringContainsString('reduced to 1', $res->customerReply);

        // Turn 2: Customer asks to "remove sneaker" -> should remove item completely
        $envRemove = new InboundEnvelope('whatsapp', '254711223344', $company->id, 'remove sneaker');
        $resRemove = $pipeline->processTurn($company, $chat, $envRemove, $channelAdapter);

        $itemsAfter = $chat->fresh()->order_draft['items'] ?? [];
        $this->assertCount(0, $itemsAfter);
        $this->assertStringContainsString('Removed *Black Sneakers* from your cart', $resRemove->customerReply);
    }
}
