<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductMultipartBooleanTest extends TestCase
{
    use RefreshDatabase;

    private function actingCompanyOwner(): User
    {
        $company = Company::create(['name' => 'Bool Co', 'email' => 'bool@test.local', 'status' => 'active']);
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
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_update_accepts_multipart_true_false_boolean_strings(): void
    {
        $user = $this->actingCompanyOwner();
        $product = Product::create([
            'company_id' => $user->company_id,
            'name' => 'Accessories',
            'price' => 100,
            'stock' => 11,
            'status' => 'active',
            'product_type' => 'physical',
            'fulfillment_type' => 'shipping',
            'track_inventory' => true,
            'requires_delivery_address' => true,
            'bookable' => false,
        ]);

        // Dio/FormData historically sends "true"/"false"; Laravel boolean rejects those.
        $this->post("/api/company/products/{$product->id}", [
            'name' => 'Accessories',
            'price' => 100,
            'stock' => 11,
            'productType' => 'physical',
            'fulfillmentType' => 'shipping',
            'trackInventory' => 'true',
            'requiresDeliveryAddress' => 'true',
            'bookable' => 'false',
            'status' => 'active',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('product.trackInventory', true)
            ->assertJsonPath('product.requiresDeliveryAddress', true)
            ->assertJsonPath('product.bookable', false);
    }

    public function test_update_accepts_multipart_zero_one_boolean_strings(): void
    {
        $user = $this->actingCompanyOwner();
        $product = Product::create([
            'company_id' => $user->company_id,
            'name' => 'Widget',
            'price' => 50,
            'stock' => 2,
            'status' => 'active',
            'product_type' => 'physical',
            'fulfillment_type' => 'shipping',
            'track_inventory' => true,
            'requires_delivery_address' => true,
        ]);

        $this->post("/api/company/products/{$product->id}", [
            'stock' => 5,
            'trackInventory' => '0',
            'requiresDeliveryAddress' => '1',
        ])->assertOk()
            ->assertJsonPath('product.trackInventory', false)
            ->assertJsonPath('product.requiresDeliveryAddress', true)
            ->assertJsonPath('product.stock', 5);
    }

    public function test_store_and_update_respects_requires_delivery_address_flag(): void
    {
        $user = $this->actingCompanyOwner();

        // 1. Create product with requiresDeliveryAddress explicitly set to false
        $res = $this->post('/api/company/products', [
            'name' => 'Online Masterclass',
            'price' => 199,
            'stock' => 100,
            'category' => 'Courses',
            'productType' => 'digital',
            'fulfillmentType' => 'download',
            'requiresDeliveryAddress' => '0',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('product.requiresDeliveryAddress', false);

        $productId = $res->json('product.id');
        $product = Product::find($productId);
        $this->assertFalse((bool) $product->requires_delivery_address);

        // 2. Update product toggling requiresDeliveryAddress to true
        $this->post("/api/company/products/{$productId}", [
            'requiresDeliveryAddress' => '1',
        ])->assertOk()
            ->assertJsonPath('product.requiresDeliveryAddress', true);

        $product->refresh();
        $this->assertTrue((bool) $product->requires_delivery_address);

        // 3. Update product using snake_case requires_delivery_address to false
        $this->post("/api/company/products/{$productId}", [
            'requires_delivery_address' => '0',
        ])->assertOk()
            ->assertJsonPath('product.requiresDeliveryAddress', false);

        $product->refresh();
        $this->assertFalse((bool) $product->requires_delivery_address);
    }
}
