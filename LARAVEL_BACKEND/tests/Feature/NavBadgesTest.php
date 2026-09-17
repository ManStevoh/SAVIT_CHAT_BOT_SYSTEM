<?php

namespace Tests\Feature;

use App\Models\Chat;
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

class NavBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_nav_badges_count_unread_chats_and_open_orders(): void
    {
        [$company, $user] = $this->companyUser();

        Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700111222',
            'customer_name' => 'Amina',
            'status' => 'active',
            'unread_count' => 3,
        ]);
        Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700333444',
            'customer_name' => 'Brian',
            'status' => 'active',
            'unread_count' => 1,
        ]);

        $ebook = Product::create([
            'company_id' => $company->id,
            'name' => 'Digital Guide',
            'price' => 10,
            'product_type' => 'digital',
            'fulfillment_type' => 'download',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'status' => 'active',
        ]);
        $mug = Product::create([
            'company_id' => $company->id,
            'name' => 'Mug',
            'price' => 20,
            'product_type' => 'physical',
            'fulfillment_type' => 'shipping',
            'track_inventory' => true,
            'stock' => 5,
            'requires_delivery_address' => true,
            'status' => 'active',
        ]);

        $digital = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-NAV-D',
            'customer_name' => 'Amina',
            'customer_phone' => '254700111222',
            'fulfillment_type' => 'delivery',
            'total' => 10,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
        OrderProduct::create([
            'order_id' => $digital->id,
            'product_id' => $ebook->id,
            'name' => $ebook->name,
            'quantity' => 1,
            'price' => 10,
            'fulfillment_data' => ['productType' => 'digital', 'fulfillmentType' => 'download'],
        ]);

        $physical = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-NAV-P',
            'customer_name' => 'Brian',
            'customer_phone' => '254700333444',
            'fulfillment_type' => 'delivery',
            'delivery_address' => 'Nairobi',
            'total' => 20,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
        OrderProduct::create([
            'order_id' => $physical->id,
            'product_id' => $mug->id,
            'name' => $mug->name,
            'quantity' => 1,
            'price' => 20,
        ]);

        Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-NAV-DONE',
            'customer_name' => 'Done',
            'customer_phone' => '254700000000',
            'total' => 5,
            'status' => 'delivered',
            'payment_status' => 'paid',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/company/nav-badges')
            ->assertOk()
            ->assertJsonPath('unreadChats', 4)
            ->assertJsonPath('readyToShip', 1)
            ->assertJsonPath('openOrders', 2);
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyUser(): array
    {
        $company = Company::create([
            'name' => 'Nav Co',
            'email' => 'nav@test.local',
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
