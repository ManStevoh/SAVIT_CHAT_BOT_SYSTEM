<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentStoreGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['deploy.secret' => 'test-secret-12345']);
        config(['deploy.agent_key' => 'test-agent-key-xyz']);
    }

    public function test_unauthorized_request_rejected_with_401(): void
    {
        $response = $this->postJson('/api/agent/store', [
            'action' => 'list_stores',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    public function test_list_stores_returns_registered_companies(): void
    {
        $company = Company::factory()->create([
            'name'       => 'Teka Coffee Co',
            'store_slug' => 'teka-coffee',
        ]);

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/api/agent/store', [
            'action' => 'list_stores',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'count',
            'stores' => [
                '*' => ['id', 'name', 'store_slug', 'status', 'products_count'],
            ],
        ]);
        $this->assertEquals('Teka Coffee Co', $response->json('stores.0.name'));
    }

    public function test_add_product_creates_item_with_unique_slug(): void
    {
        $company = Company::factory()->create([
            'name'       => 'Acme Store',
            'store_slug' => 'acme-store',
        ]);

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/api/agent/store', [
            'action'   => 'add_product',
            'store'    => $company->id,
            'name'     => 'Vanilla Bean Latte',
            'price'    => 4.75,
            'stock'    => 50,
            'category' => 'Beverages',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'product' => [
                'name'     => 'Vanilla Bean Latte',
                'price'    => 4.75,
                'stock'    => 50,
                'category' => 'Beverages',
                'status'   => 'active',
            ],
        ]);

        $this->assertDatabaseHas('products', [
            'company_id' => $company->id,
            'name'       => 'Vanilla Bean Latte',
            'slug'       => 'vanilla-bean-latte',
        ]);
    }

    public function test_list_products_returns_store_items(): void
    {
        $company = Company::factory()->create();
        Product::create([
            'company_id'       => $company->id,
            'name'             => 'Product A',
            'slug'             => 'product-a',
            'price'            => 10.00,
            'stock'            => 15,
            'status'           => 'active',
            'fulfillment_type' => 'manual',
            'product_type'     => 'physical',
        ]);

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/api/agent/store', [
            'action' => 'list_products',
            'store'  => $company->id,
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('products'));
        $this->assertEquals('Product A', $response->json('products.0.name'));
    }

    public function test_update_product_modifies_attributes(): void
    {
        $company = Company::factory()->create();
        $product = Product::create([
            'company_id'       => $company->id,
            'name'             => 'Product Original',
            'slug'             => 'product-original',
            'price'            => 10.00,
            'stock'            => 5,
            'status'           => 'active',
            'fulfillment_type' => 'manual',
            'product_type'     => 'physical',
        ]);

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/api/agent/store', [
            'action'     => 'update_product',
            'store'      => $company->id,
            'product_id' => $product->id,
            'updates'    => [
                'price' => 12.50,
                'stock' => 20,
            ],
        ]);

        $response->assertOk();
        $this->assertEquals(12.50, $response->json('product.price'));
        $this->assertEquals(20, $response->json('product.stock'));

        $this->assertDatabaseHas('products', [
            'id'    => $product->id,
            'price' => 12.50,
            'stock' => 20,
        ]);
    }

    public function test_delete_product_archives_by_default(): void
    {
        $company = Company::factory()->create();
        $product = Product::create([
            'company_id'       => $company->id,
            'name'             => 'Product To Archive',
            'slug'             => 'product-to-archive',
            'price'            => 10.00,
            'stock'            => 5,
            'status'           => 'active',
            'fulfillment_type' => 'manual',
            'product_type'     => 'physical',
        ]);

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/api/agent/store', [
            'action'     => 'remove_product',
            'store'      => $company->id,
            'product_id' => $product->id,
        ]);

        $response->assertOk();
        $this->assertEquals('archived', $response->json('action'));

        $this->assertDatabaseHas('products', [
            'id'     => $product->id,
            'status' => 'inactive',
        ]);
    }

    public function test_force_delete_permanently_removes_product(): void
    {
        $company = Company::factory()->create();
        $product = Product::create([
            'company_id'       => $company->id,
            'name'             => 'Product To Delete',
            'slug'             => 'product-to-delete',
            'price'            => 10.00,
            'stock'            => 5,
            'status'           => 'active',
            'fulfillment_type' => 'manual',
            'product_type'     => 'physical',
        ]);

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/api/agent/store', [
            'action'       => 'remove_product',
            'store'        => $company->id,
            'product_id'   => $product->id,
            'force_delete' => true,
        ]);

        $response->assertOk();
        $this->assertEquals('deleted', $response->json('action'));

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_bulk_import_creates_multiple_items(): void
    {
        $company = Company::factory()->create();

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/api/agent/store', [
            'action' => 'bulk_import',
            'store'  => $company->id,
            'items'  => [
                ['name' => 'Item 1', 'price' => 5.00, 'stock' => 10],
                ['name' => 'Item 2', 'price' => 7.50, 'stock' => 20],
                ['name' => 'Item 3', 'price' => 12.00, 'stock' => 30],
            ],
        ]);

        $response->assertOk();
        $this->assertEquals(3, $response->json('created_count'));
        $this->assertDatabaseCount('products', 3);
    }
}
