<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Product;
use App\Services\Agent\CheckoutMessageComposer;
use App\Services\OrderFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderProceedLoopFixTest extends TestCase
{
    use RefreshDatabase;

    private function seedTestEnvironment(): array
    {
        $company = Company::create([
            'name' => 'Ken Businesses',
            'email' => 'ken@test.local',
            'status' => 'active',
        ]);

        CompanySetting::create([
            'company_id' => $company->id,
            'orders_collect_payment_enabled' => true,
            'order_payment_manual_enabled' => true,
            'order_payment_manual_instructions' => "Pay via Till 12345",
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Red Headphones',
            'price' => 150.00,
            'stock' => 50,
            'status' => 'active',
            'requires_delivery_address' => false,
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254711223344',
            'customer_name' => 'Ken',
            'status' => 'active',
            'last_message' => 'i want red headphones',
            'last_message_at' => now(),
        ]);

        return [$company, $chat, $product];
    }

    public function test_order_flow_places_order_on_conversational_proceed_phrases(): void
    {
        [$company, $chat, $product] = $this->seedTestEnvironment();

        $draft = [
            'items' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'quantity' => 1,
            ]],
        ];

        $chat->update([
            'conversation_step' => OrderFlowService::STEP_CONFIRM,
            'order_draft' => $draft,
        ]);

        $orderFlow = app(OrderFlowService::class);

        $reply = $orderFlow->processMessage(
            $chat->fresh(),
            $company->fresh(['settings']),
            'i am ready to proceed with the order',
            'Ken',
            '254711223344'
        );

        $this->assertNotNull($reply);
        $this->assertTrue(Order::where('company_id', $company->id)->exists());
    }

    public function test_step_address_rejects_affirmation_phrases_as_delivery_address(): void
    {
        [$company, $chat, $product] = $this->seedTestEnvironment();

        $chat->update([
            'conversation_step' => OrderFlowService::STEP_ADDRESS,
            'order_draft' => [
                'items' => [[
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => 150.00,
                    'quantity' => 1,
                ]],
            ],
        ]);

        $orderFlow = app(OrderFlowService::class);

        $reply = $orderFlow->processMessage(
            $chat->fresh(),
            $company->fresh(['settings']),
            'yes proceed with the order',
            'Ken',
            '254711223344'
        );

        $this->assertStringContainsString('delivery address', strtolower((string) $reply));
        $this->assertEquals(OrderFlowService::STEP_ADDRESS, $chat->fresh()->conversation_step);
    }

    public function test_checkout_composer_recognizes_conversational_affirmation(): void
    {
        $composer = app(CheckoutMessageComposer::class);

        $this->assertTrue($composer->looksLikeAffirm('i am ready to proceed with the order'));
        $this->assertTrue($composer->looksLikeAffirm('want to proceed with ordering the red headphones'));
        $this->assertTrue($composer->looksLikeAffirm('yes proceed'));
        $this->assertTrue($composer->looksLikeAffirm('confirm order'));
    }
}
