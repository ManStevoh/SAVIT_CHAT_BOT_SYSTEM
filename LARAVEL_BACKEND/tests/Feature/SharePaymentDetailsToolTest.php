<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Tools\SharePaymentDetailsTool;
use App\Services\Orders\OrderPaymentDetailsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharePaymentDetailsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_shares_manual_till_instructions_for_unpaid_order(): void
    {
        $company = Company::create([
            'name' => 'Wafula Stores',
            'email' => 'wafula-pay@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'display_currency' => 'KES',
            'orders_accept_paystack' => false,
            'orders_accept_mpesa' => false,
            'orders_accept_stripe' => false,
            'order_payment_manual_instructions' => 'till 123456',
            'agent_commerce_enabled' => true,
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_name' => 'Buyer',
            'customer_phone' => '254700111222',
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-PAY-1',
            'customer_name' => 'Buyer',
            'customer_phone' => '254700111222',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 2000,
        ]);
        OrderProduct::create([
            'order_id' => $order->id,
            'name' => 'Headphones',
            'quantity' => 10,
            'price' => 200,
        ]);

        $context = new AgentToolContext(
            company: $company->fresh(['settings']),
            chat: $chat,
            customerPhone: '254700111222',
            customerName: 'Buyer',
            incomingMessage: 'share with me payment details',
        );

        $result = app(SharePaymentDetailsTool::class)->execute($context, []);

        $this->assertTrue($result['success'] ?? false, json_encode($result));
        $this->assertSame('ORD-PAY-1', $result['order_number']);
        $this->assertContains('manual', $result['methods']);
        $this->assertStringContainsString('till 123456', $result['customer_message']);
        $this->assertStringNotContainsString('being set up', strtolower($result['customer_message']));
    }

    public function test_payment_prompt_block_includes_manual_instructions(): void
    {
        $company = Company::create([
            'name' => 'Pay Co',
            'email' => 'payco@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'order_payment_manual_instructions' => 'till 123456',
        ]);

        $block = app(OrderPaymentDetailsService::class)->promptBlockForCompany($company->fresh(['settings']));
        $this->assertStringContainsString('till 123456', $block);
        $this->assertStringContainsString('share_payment_details', $block);
    }
}
