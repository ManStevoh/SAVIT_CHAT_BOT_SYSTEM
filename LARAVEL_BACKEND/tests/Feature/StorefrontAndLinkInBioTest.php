<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontAndLinkInBioTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: Product}
     */
    private function seedStorefront(): array
    {
        $company = Company::create([
            'name' => 'Wafulla Stores',
            'email' => 'wafulla@test.local',
            'status' => 'active',
            'store_slug' => 'wafulla',
            'storefront_enabled' => true,
            'link_in_bio_enabled' => true,
            'link_in_bio_headline' => 'Shop Wafulla Stores',
            'link_in_bio_bio' => 'Quality goods, fast delivery.',
            'link_in_bio_links' => [
                ['label' => 'Instagram', 'url' => 'https://instagram.com/wafulla'],
            ],
        ]);

        CompanySetting::create([
            'company_id' => $company->id,
            'orders_accept_cod' => true,
            'whatsapp_number' => '254700111222',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Headphones',
            'price' => 20,
            'stock' => 100,
            'status' => 'active',
            'product_type' => 'physical',
            'fulfillment_type' => 'shipping',
            'track_inventory' => false,
            'requires_delivery_address' => false,
        ]);

        return [$company, $product];
    }

    public function test_storefront_home_returns_ok(): void
    {
        [$company] = $this->seedStorefront();

        $response = $this->get("/s/{$company->store_slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('store/page')
            ->has('products', 1)
        );
    }

    public function test_add_to_cart_and_checkout_creates_order_with_pay_token(): void
    {
        [$company, $product] = $this->seedStorefront();
        $slug = $company->store_slug;

        $this->post("/s/{$slug}/cart", [
            'productId' => $product->id,
            'quantity' => 2,
        ])->assertRedirect("/s/{$slug}/cart");

        $this->get("/s/{$slug}/cart")->assertStatus(200)->assertInertia(fn ($page) => $page
            ->component('store/cart')
            ->where('cart.itemCount', 2)
        );

        $checkoutResponse = $this->post("/s/{$slug}/checkout", [
            'customerName' => 'Jane Buyer',
            'customerPhone' => '254711222333',
            'fulfillmentType' => 'pickup',
        ]);

        $order = Order::where('company_id', $company->id)->first();
        $this->assertNotNull($order, 'Order should have been created.');
        $this->assertNotEmpty($order->pay_token);
        $this->assertNotEmpty($order->invoice_token);
        $this->assertSame('storefront', $order->source);
        $this->assertSame(40.0, (float) $order->total);

        $checkoutResponse->assertRedirect("/s/{$slug}/order/{$order->pay_token}");

        $this->get("/s/{$slug}/order/{$order->pay_token}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('store/confirmation'));
    }

    public function test_pay_page_returns_ok_for_valid_token(): void
    {
        [$company, $product] = $this->seedStorefront();
        $slug = $company->store_slug;

        $this->post("/s/{$slug}/cart", ['productId' => $product->id, 'quantity' => 1]);
        $this->post("/s/{$slug}/checkout", [
            'customerName' => 'Jane Buyer',
            'customerPhone' => '254711222333',
            'fulfillmentType' => 'pickup',
        ]);

        $order = Order::where('company_id', $company->id)->firstOrFail();

        $this->get("/pay/{$order->pay_token}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('pay/page'));
    }

    public function test_link_in_bio_page_returns_ok_when_enabled(): void
    {
        [$company] = $this->seedStorefront();

        $this->get("/b/{$company->store_slug}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('bio/page')
                ->where('company.headline', 'Shop Wafulla Stores')
            );
    }

    public function test_invoice_page_returns_ok_for_valid_token(): void
    {
        [$company, $product] = $this->seedStorefront();
        $slug = $company->store_slug;

        $this->post("/s/{$slug}/cart", ['productId' => $product->id, 'quantity' => 1]);
        $this->post("/s/{$slug}/checkout", [
            'customerName' => 'Jane Buyer',
            'customerPhone' => '254711222333',
            'fulfillmentType' => 'pickup',
        ]);

        $order = Order::where('company_id', $company->id)->firstOrFail();

        $this->get("/invoice/{$order->invoice_token}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('invoice/page'));
    }
}
