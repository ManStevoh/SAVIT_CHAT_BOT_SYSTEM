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
}
