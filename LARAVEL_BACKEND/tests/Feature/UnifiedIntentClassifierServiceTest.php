<?php

namespace Tests\Feature;

use App\DTOs\IntentResult;
use App\Enums\CommerceIntent;
use App\Models\Chat;
use App\Models\Company;
use App\Services\AI\AiGateway;
use App\Services\AI\UnifiedIntentClassifierService;
use App\Services\OrderFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class UnifiedIntentClassifierServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_classifies_add_to_cart_intent(): void
    {
        $company = Company::factory()->create();
        $chat = Chat::factory()->create(['company_id' => $company->id]);

        $mockGateway = Mockery::mock(AiGateway::class);
        $mockGateway->shouldReceive('completeWithJson')
            ->once()
            ->andReturn([
                'content' => json_encode([
                    'intent' => 'add_to_cart',
                    'confidence' => 0.96,
                    'entities' => [
                        'product' => 'Wireless Earphones',
                        'quantity' => 2,
                    ],
                    'requires_clarification' => false,
                ]),
                'success' => true,
                'model' => 'gpt-5-mini',
            ]);

        $classifier = new UnifiedIntentClassifierService($mockGateway);
        $result = $classifier->classify($company, $chat, 'I want to buy 2 wireless earphones');

        $this->assertInstanceOf(IntentResult::class, $result);
        $this->assertEquals(CommerceIntent::ADD_TO_CART, $result->intent);
        $this->assertEquals(0.96, $result->confidence);
        $this->assertEquals('Wireless Earphones', $result->product);
        $this->assertEquals(2, $result->quantity);
        $this->assertTrue($result->intent->isPhase1Eligible());
    }

    public function test_order_flow_handles_update_quantity_structured_intent(): void
    {
        $company = Company::factory()->create();
        $chat = Chat::factory()->create([
            'company_id' => $company->id,
            'conversation_step' => 'product',
            'order_draft' => [
                'items' => [
                    [
                        'product_id' => 10,
                        'name' => 'Red Shirt',
                        'price' => 25.0,
                        'quantity' => 1,
                    ],
                ],
            ],
        ]);

        $intent = IntentResult::fromArray([
            'intent' => 'update_quantity',
            'confidence' => 0.92,
            'entities' => [
                'product' => 'Red Shirt',
                'quantity' => 4,
            ],
            'requires_clarification' => false,
        ]);

        $orderFlow = app(OrderFlowService::class);
        $result = $orderFlow->handleStructuredCartIntent($intent, $chat, $company);

        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Updated *Red Shirt* quantity to 4', $result['message']);

        $chat->refresh();
        $updatedDraft = $chat->order_draft;
        $this->assertEquals(4, $updatedDraft['items'][0]['quantity']);
    }
}
