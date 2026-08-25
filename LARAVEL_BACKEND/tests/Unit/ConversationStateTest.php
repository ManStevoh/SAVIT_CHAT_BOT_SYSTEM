<?php

namespace Tests\Unit;

use App\DTOs\ConversationState;
use App\Enums\CheckoutStep;
use App\Models\Chat;
use App\Services\Conversation\ConversationStateHydrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_step_enum_converts_to_and_from_legacy_strings(): void
    {
        $this->assertEquals(CheckoutStep::BUILDING_CART, CheckoutStep::fromLegacyStep('product'));
        $this->assertEquals(CheckoutStep::COLLECTING_ADDRESS, CheckoutStep::fromLegacyStep('address'));
        $this->assertEquals(CheckoutStep::REVIEWING_ORDER, CheckoutStep::fromLegacyStep('confirm'));
        $this->assertEquals(CheckoutStep::IDLE, CheckoutStep::fromLegacyStep(null));

        $this->assertEquals('product', CheckoutStep::BUILDING_CART->toLegacyStep());
        $this->assertEquals('address', CheckoutStep::COLLECTING_ADDRESS->toLegacyStep());
        $this->assertEquals(null, CheckoutStep::IDLE->toLegacyStep());
    }

    public function test_conversation_state_with_method_produces_new_immutable_instance(): void
    {
        $state = new ConversationState(
            chatId: 10,
            companyId: 1,
            customerPhone: '254700000000',
            customerName: 'Alice',
            step: CheckoutStep::IDLE,
        );

        $nextState = $state->with([
            'step' => CheckoutStep::COLLECTING_ADDRESS,
            'deliveryAddress' => '123 Main St',
        ]);

        $this->assertNotSame($state, $nextState);
        $this->assertEquals(CheckoutStep::IDLE, $state->step);
        $this->assertNull($state->deliveryAddress);
        $this->assertEquals(1, $state->version);

        $this->assertEquals(CheckoutStep::COLLECTING_ADDRESS, $nextState->step);
        $this->assertEquals('123 Main St', $nextState->deliveryAddress);
        $this->assertEquals(2, $nextState->version);
    }

    public function test_conversation_state_hydrator_hydrates_and_dehydrates_chat_model(): void
    {
        $chat = Chat::create([
            'company_id' => 1,
            'customer_phone' => '254711999888',
            'customer_name' => 'Bob',
            'status' => 'active',
            'conversation_step' => 'product',
            'order_draft' => [
                'items' => [[
                    'product_id' => 5,
                    'name' => 'Sample Item',
                    'price' => 100.00,
                    'quantity' => 2,
                ]],
                'fulfillment_type' => 'delivery',
            ],
        ]);

        $hydrator = new ConversationStateHydrator();
        $state = $hydrator->hydrateFromChat($chat);

        $this->assertEquals($chat->id, $state->chatId);
        $this->assertEquals(CheckoutStep::BUILDING_CART, $state->step);
        $this->assertCount(1, $state->cartItems);
        $this->assertEquals('Sample Item', $state->cartItems[0]['name']);

        $updatedState = $state->with([
            'step' => CheckoutStep::REVIEWING_ORDER,
            'deliveryAddress' => 'Nairobi West, Block B',
        ]);

        $hydrator->dehydrateToChat($updatedState, $chat);

        $chat->refresh();
        $this->assertEquals('confirm', $chat->conversation_step);
        $this->assertEquals('Nairobi West, Block B', $chat->order_draft['delivery_address']);
    }

    public function test_calculate_cart_total(): void
    {
        $state = new ConversationState(
            chatId: 1,
            companyId: 1,
            customerPhone: '254700000000',
            customerName: 'Test',
            step: CheckoutStep::BUILDING_CART,
            cartItems: [
                ['name' => 'Item A', 'price' => 150.50, 'quantity' => 2],
                ['name' => 'Item B', 'price' => 99.00, 'quantity' => 1],
            ]
        );

        $this->assertTrue($state->hasItems());
        $this->assertEquals(400.00, $state->calculateCartTotal());
    }
}
