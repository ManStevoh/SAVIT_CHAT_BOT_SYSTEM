<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReceiptFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_receipt_shows_digital_access_details(): void
    {
        $company = Company::create([
            'name' => 'Fulfillment Co',
            'email' => 'orders@fulfillment.test',
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-FULFILL',
            'customer_name' => 'Jane Customer',
            'customer_phone' => '254700111222',
            'total' => 29,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        OrderProduct::create([
            'order_id' => $order->id,
            'name' => 'AI Sales Course',
            'quantity' => 1,
            'price' => 29,
            'fulfillment_data' => [
                'productType' => 'digital',
                'fulfillmentType' => 'link',
                'fulfillmentInstructions' => 'Use the private portal link below.',
                'accessUrl' => 'https://example.com/private-course',
                'digitalFileUrl' => 'https://example.com/files/guide.pdf',
                'digitalFileName' => 'guide.pdf',
            ],
        ]);

        $response = $this->get($order->publicReceiptUrl());

        $response->assertOk();
        $response->assertSee('Access & fulfillment', false);
        $response->assertSee('AI Sales Course');
        $response->assertSee('https://example.com/private-course', false);
        $response->assertSee('guide.pdf');
    }
}
