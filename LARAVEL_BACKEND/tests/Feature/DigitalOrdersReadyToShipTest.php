<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DigitalOrdersReadyToShipTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_digital_orders_are_not_ready_to_ship(): void
    {
        [$company, $user] = $this->companyUser();

        $ebook = Product::create([
            'company_id' => $company->id,
            'name' => 'The Figure and the Mirror',
            'price' => 350,
            'category' => 'Books',
            'product_type' => 'digital',
            'fulfillment_type' => 'download',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'status' => 'active',
        ]);

        $widget = Product::create([
            'company_id' => $company->id,
            'name' => 'Ceramic Mug',
            'price' => 800,
            'category' => 'Goods',
            'product_type' => 'physical',
            'fulfillment_type' => 'shipping',
            'track_inventory' => true,
            'requires_delivery_address' => true,
            'stock' => 10,
            'status' => 'active',
        ]);

        $digitalOrder = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-DIGITAL1',
            'customer_name' => 'Brenda Katana',
            'customer_phone' => '074023429',
            'fulfillment_type' => 'delivery',
            'total' => 350,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
        OrderProduct::create([
            'order_id' => $digitalOrder->id,
            'product_id' => $ebook->id,
            'name' => $ebook->name,
            'quantity' => 1,
            'price' => 350,
            'fulfillment_data' => [
                'productType' => 'digital',
                'fulfillmentType' => 'download',
            ],
        ]);

        $physicalOrder = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-PHYSICAL1',
            'customer_name' => 'Ken Wafula',
            'customer_phone' => '254112576616',
            'fulfillment_type' => 'delivery',
            'delivery_address' => 'Nairobi',
            'total' => 800,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
        OrderProduct::create([
            'order_id' => $physicalOrder->id,
            'product_id' => $widget->id,
            'name' => $widget->name,
            'quantity' => 1,
            'price' => 800,
        ]);

        Sanctum::actingAs($user);

        $ready = $this->getJson('/api/company/orders?status=waiting_shipping')
            ->assertOk()
            ->json();

        $this->assertSame(1, $ready['total']);
        $this->assertSame('ORD-PHYSICAL1', $ready['orders'][0]['orderNumber']);
        $this->assertTrue($ready['orders'][0]['needsShipping']);

        $listed = $this->getJson('/api/company/orders/'.$digitalOrder->id)
            ->assertOk()
            ->json('order');

        $this->assertFalse($listed['needsShipping']);
        $this->assertSame('digital', $listed['fulfillmentType']);
        $this->assertSame('digital', $listed['products'][0]['productType']);
        $this->assertFalse($listed['products'][0]['needsShipping']);
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyUser(): array
    {
        $company = Company::create([
            'name' => 'Ebook Co',
            'email' => 'ebooks@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create(['company_id' => $company->id]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return [$company, $user];
    }
}
