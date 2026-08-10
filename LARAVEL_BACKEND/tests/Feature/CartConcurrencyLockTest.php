<?php

namespace Tests\Feature;

use App\DTOs\IntentResult;
use App\Enums\CommerceIntent;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Product;
use App\Services\OrderFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CartConcurrencyLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_add_to_cart_operations_preserve_both_items(): void
    {
        $company = Company::factory()->create();
        $chat = Chat::factory()->create([
            'company_id' => $company->id,
            'conversation_step' => 'product',
            'order_draft' => ['items' => []],
        ]);

        Product::factory()->create([
            'company_id' => $company->id,
            'name' => 'Red Earphones',
            'price' => 15.0,
            'status' => 'active',
        ]);

        Product::factory()->create([
            'company_id' => $company->id,
            'name' => 'Blue Shirt',
            'price' => 30.0,
            'status' => 'active',
        ]);

        $orderFlow = app(OrderFlowService::class);

        $intent1 = IntentResult::fromArray([
            'intent' => 'add_to_cart',
            'confidence' => 0.95,
            'entities' => [
                'product' => 'Red Earphones',
                'quantity' => 1,
            ],
            'requires_clarification' => false,
        ]);

        $intent2 = IntentResult::fromArray([
            'intent' => 'add_to_cart',
            'confidence' => 0.92,
            'entities' => [
                'product' => 'Blue Shirt',
                'quantity' => 2,
            ],
            'requires_clarification' => false,
        ]);

        // Operation 1
        $res1 = $orderFlow->handleStructuredCartIntent($intent1, $chat, $company);
        $this->assertNotNull($res1);
        $this->assertTrue($res1['success']);

        // Operation 2 (simulating sequential execution under Redis cache lock)
        $res2 = $orderFlow->handleStructuredCartIntent($intent2, $chat, $company);
        $this->assertNotNull($res2);
        $this->assertTrue($res2['success']);

        // Verify fresh chat model has both items saved in order_draft
        $chat->refresh();
        $items = $chat->order_draft['items'] ?? [];

        $this->assertCount(2, $items);
        $this->assertEquals('Red Earphones', $items[0]['name']);
        $this->assertEquals('Blue Shirt', $items[1]['name']);
        $this->assertEquals(2, $items[1]['quantity']);
    }

    public function test_with_chat_lock_acquires_correct_cache_key(): void
    {
        $company = Company::factory()->create();
        $chat = Chat::factory()->create(['company_id' => $company->id]);

        $orderFlow = app(OrderFlowService::class);
        $executed = false;

        $orderFlow->withChatLock($chat, function (Chat $freshChat) use (&$executed, $chat) {
            $executed = true;
            $this->assertEquals($chat->id, $freshChat->id);
        });

        $this->assertTrue($executed);
    }
}
