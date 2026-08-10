<?php

namespace Tests\Unit;

use App\DTOs\ConversationState;
use App\DTOs\IntentResult;
use App\Enums\CheckoutStep;
use App\Enums\CommerceIntent;
use App\Enums\ResponseSpec;
use App\Models\Company;
use App\Models\Product;
use App\Services\OrderFlowService;
use App\Services\Workflow\DomainServiceDispatcher;
use App\Services\Workflow\ResponseSpecRenderer;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompanyAndProduct(): array
    {
        $company = Company::create([
            'name' => 'Wafulla Electronics',
            'email' => 'wafulla@test.local',
            'status' => 'active',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Red Headphones',
            'price' => 150.00,
            'stock' => 10,
            'status' => 'active',
        ]);

        return [$company, $product];
    }

    public function test_workflow_engine_adds_item_to_cart_on_add_to_cart_intent(): void
    {
        [$company, $product] = $this->seedCompanyAndProduct();

        $engine = new WorkflowEngine(
            new DomainServiceDispatcher(app(OrderFlowService::class)),
            new ResponseSpecRenderer()
        );

        $state = new ConversationState(
            chatId: 1,
            companyId: $company->id,
            customerPhone: '254700111222',
            customerName: 'Ken',
            step: CheckoutStep::IDLE
        );

        $intent = new IntentResult(
            intent: CommerceIntent::ADD_TO_CART,
            confidence: 0.95,
            product: 'Red Headphones',
            quantity: 1
        );

        $result = $engine->handle($state, $intent, $company);

        $this->assertEquals(CheckoutStep::BUILDING_CART, $result->nextState->step);
        $this->assertCount(1, $result->nextState->cartItems);
        $this->assertEquals('Red Headphones', $result->nextState->cartItems[0]['name']);
        $this->assertEquals(ResponseSpec::CART_SUMMARY->value, $result->responseSpec);
        $this->assertStringContainsString('Added to cart', (string) $result->customerReply);
    }

    public function test_workflow_engine_transitions_to_address_step_on_checkout_intent(): void
    {
        [$company, $product] = $this->seedCompanyAndProduct();

        $engine = new WorkflowEngine(
            new DomainServiceDispatcher(app(OrderFlowService::class)),
            new ResponseSpecRenderer()
        );

        $state = new ConversationState(
            chatId: 1,
            companyId: $company->id,
            customerPhone: '254700111222',
            customerName: 'Ken',
            step: CheckoutStep::BUILDING_CART,
            cartItems: [[
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 150.00,
                'quantity' => 1,
            ]]
        );

        $intent = new IntentResult(
            intent: CommerceIntent::START_CHECKOUT,
            confidence: 0.92
        );

        $result = $engine->handle($state, $intent, $company);

        $this->assertEquals(CheckoutStep::COLLECTING_ADDRESS, $result->nextState->step);
        $this->assertEquals(ResponseSpec::PROMPT_DELIVERY_ADDRESS->value, $result->responseSpec);
    }

    public function test_workflow_engine_transitions_to_order_review_on_valid_address(): void
    {
        [$company, $product] = $this->seedCompanyAndProduct();

        $engine = new WorkflowEngine(
            new DomainServiceDispatcher(app(OrderFlowService::class)),
            new ResponseSpecRenderer()
        );

        $state = new ConversationState(
            chatId: 1,
            companyId: $company->id,
            customerPhone: '254700111222',
            customerName: 'Ken',
            step: CheckoutStep::COLLECTING_ADDRESS,
            cartItems: [[
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 150.00,
                'quantity' => 1,
            ]]
        );

        $intent = new IntentResult(
            intent: CommerceIntent::PROVIDE_ADDRESS,
            confidence: 0.90,
            address: 'Moi Avenue, Block 4, Nairobi'
        );

        $result = $engine->handle($state, $intent, $company);

        $this->assertEquals(CheckoutStep::REVIEWING_ORDER, $result->nextState->step);
        $this->assertEquals('Moi Avenue, Block 4, Nairobi', $result->nextState->deliveryAddress);
        $this->assertEquals(ResponseSpec::PROMPT_ORDER_CONFIRMATION->value, $result->responseSpec);
    }

    public function test_workflow_engine_allows_changing_payment_method_when_awaiting_payment(): void
    {
        [$company, $product] = $this->seedCompanyAndProduct();

        $engine = new WorkflowEngine(
            new DomainServiceDispatcher(app(OrderFlowService::class)),
            new ResponseSpecRenderer()
        );

        $state = new ConversationState(
            chatId: 1,
            companyId: $company->id,
            customerPhone: '254700111222',
            customerName: 'Ken',
            step: CheckoutStep::ORDER_COMPLETED,
            pendingOrderId: 28
        );

        // Test general payment change request
        $intentChange = new IntentResult(
            intent: CommerceIntent::GENERAL_CHAT,
            confidence: 0.90,
            messageText: 'i want to choose another payment method'
        );

        $resultChange = $engine->handle($state, $intentChange, $company);
        $this->assertEquals(CheckoutStep::SELECTING_PAYMENT_METHOD, $resultChange->nextState->step);
        $this->assertEquals(ResponseSpec::PROMPT_PAYMENT_SELECTION->value, $resultChange->responseSpec);

        // Test explicit payment method request (e.g. paystack)
        $intentPaystack = new IntentResult(
            intent: CommerceIntent::GENERAL_CHAT,
            confidence: 0.90,
            messageText: 'i wanna pay using paystack'
        );

        $resultPaystack = $engine->handle($state, $intentPaystack, $company);
        $this->assertStringContainsString('PAYSTACK Payment', (string) $resultPaystack->customerReply);
    }
}
