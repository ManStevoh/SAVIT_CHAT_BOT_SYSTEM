<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
            ->assertInertia(fn ($page) => $page
                ->component('pay/page')
                ->where('paymentOptions.options.0.id', 'cod')
            );
    }

    public function test_public_pay_page_keeps_every_method_returned_by_the_gateway_registry(): void
    {
        [$company, $product] = $this->seedStorefront();
        $company->settings->update([
            'order_payment_manual_instructions' => 'Till number 123456',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-PUBLIC-PAY-1',
            'customer_name' => 'Jane Buyer',
            'customer_phone' => '254711222333',
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 20,
            'pay_token' => 'public-payment-options-token',
        ]);

        $this->get("/pay/{$order->pay_token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pay/page')
                ->where('paymentOptions.options.0.id', 'cod')
                ->where('paymentOptions.options.1.id', 'manual')
                ->where('paymentOptions.options.1.instructions', 'Till number 123456')
            );
    }

    public function test_public_pay_action_rejects_gateway_not_returned_by_the_registry(): void
    {
        [$company] = $this->seedStorefront();
        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-PUBLIC-PAY-2',
            'customer_name' => 'Jane Buyer',
            'customer_phone' => '254711222333',
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 20,
            'pay_token' => 'unavailable-gateway-token',
        ]);

        $this->from("/pay/{$order->pay_token}")
            ->post("/pay/{$order->pay_token}", ['method' => 'paystack'])
            ->assertRedirect("/pay/{$order->pay_token}")
            ->assertSessionHasErrors('method');
    }

    public function test_public_pay_paystack_returns_inertia_location_for_external_redirect(): void
    {
        PaymentGateway::create([
            'slug' => 'paystack',
            'name' => 'Paystack',
            'is_enabled' => true,
            'config' => ['secret_key' => 'sk_test_platform', 'public_key' => 'pk_test_platform'],
        ]);

        [$company] = $this->seedStorefront();
        $company->settings->update([
            'orders_collect_payment_enabled' => true,
            'orders_accept_paystack' => true,
            'order_payment_paystack_config' => [
                'secret_key' => 'sk_test_tenant',
                'public_key' => 'pk_test_tenant',
                'currency' => 'kes',
                'env' => 'sandbox',
            ],
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-PUBLIC-PAYSTACK',
            'customer_name' => 'Jane Buyer',
            'customer_phone' => '254711222333',
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 696,
            'pay_token' => 'public-paystack-token',
        ]);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test-auth',
                    'reference' => 'essem_ord_test_ref',
                ],
            ], 200),
        ]);

        $this->from("/pay/{$order->pay_token}")
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post("/pay/{$order->pay_token}", ['method' => 'paystack'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://checkout.paystack.com/test-auth');
    }

    public function test_paystack_payment_complete_route_redirects_to_public_pay_page(): void
    {
        PaymentGateway::create([
            'slug' => 'paystack',
            'name' => 'Paystack',
            'is_enabled' => true,
            'config' => ['secret_key' => 'sk_test_platform', 'public_key' => 'pk_test_platform'],
        ]);

        [$company] = $this->seedStorefront();
        $company->settings->update([
            'orders_collect_payment_enabled' => true,
            'orders_accept_paystack' => true,
            'order_payment_paystack_config' => [
                'secret_key' => 'sk_test_tenant',
                'public_key' => 'pk_test_tenant',
                'currency' => 'kes',
                'env' => 'sandbox',
            ],
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-PAYSTACK-DONE',
            'customer_name' => 'Jane Buyer',
            'customer_phone' => '254711222333',
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 696,
            'pay_token' => 'paystack-complete-token',
        ]);

        $reference = 'essem_ord_'.$order->id.'_abc123';
        Cache::put(
            \App\Services\PaystackService::CACHE_KEY_ORDER_PREFIX.$reference,
            ['order_id' => $order->id],
            now()->addMinutes(30)
        );

        Http::fake([
            'api.paystack.co/transaction/verify/'.$reference => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 69600,
                    'reference' => $reference,
                ],
            ], 200),
        ]);

        $this->get('/orders/payment-complete?reference='.$reference.'&trxref='.$reference)
            ->assertRedirect('/pay/paystack-complete-token')
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
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
