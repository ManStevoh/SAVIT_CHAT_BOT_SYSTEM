<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\StorefrontCoupon;
use App\Models\StorefrontEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClassicStorefrontFeaturesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: Product, 2: Product}
     */
    private function seedStore(): array
    {
        $company = Company::create([
            'name' => 'Classic Shop Co',
            'email' => 'classic@test.local',
            'status' => 'active',
            'store_slug' => 'classic-shop',
            'storefront_enabled' => true,
            'storefront_theme' => ['primary_color' => '#112233', 'announcement_bar' => 'Free shipping today'],
            'storefront_sections' => [
                ['type' => 'hero', 'headline' => 'Welcome', 'subhead' => 'Shop with us'],
                ['type' => 'catalog'],
            ],
        ]);

        CompanySetting::create([
            'company_id' => $company->id,
            'orders_accept_cod' => true,
            'whatsapp_number' => '254700999888',
            'abandoned_cart_recovery_enabled' => true,
            'display_currency' => 'KES',
        ]);

        $latte = Product::create([
            'company_id' => $company->id,
            'name' => 'Vanilla Latte',
            'slug' => 'vanilla-latte',
            'description' => 'Creamy latte drink',
            'meta_title' => 'Vanilla Latte SEO',
            'price' => 5,
            'compare_at_price' => 8,
            'stock' => 10,
            'status' => 'active',
            'product_type' => 'physical',
            'category' => 'Drinks',
            'track_inventory' => true,
        ]);

        $muffin = Product::create([
            'company_id' => $company->id,
            'name' => 'Blueberry Muffin',
            'slug' => 'blueberry-muffin',
            'price' => 3,
            'stock' => 0,
            'status' => 'active',
            'product_type' => 'physical',
            'category' => 'Bakery',
            'track_inventory' => true,
        ]);

        return [$company, $latte, $muffin];
    }

    public function test_catalog_search_and_sort_filters(): void
    {
        [$company] = $this->seedStore();
        $slug = $company->store_slug;

        $this->get("/s/{$slug}?q=latte")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('store/page')
                ->has('products', 1)
                ->where('products.0.name', 'Vanilla Latte')
            );

        $this->get("/s/{$slug}?sort=price_asc")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('products.0.name', 'Blueberry Muffin')
            );
    }

    public function test_sold_out_product_cannot_be_added_to_cart(): void
    {
        [$company, , $muffin] = $this->seedStore();
        $slug = $company->store_slug;

        $this->from("/s/{$slug}/p/{$muffin->slug}")
            ->post("/s/{$slug}/cart", [
                'productId' => $muffin->id,
                'quantity' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('productId');
    }

    public function test_product_slug_seo_route_and_related_serialization(): void
    {
        [$company, $latte] = $this->seedStore();

        $this->get("/s/{$company->store_slug}/p/{$latte->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('store/product')
                ->where('product.slug', 'vanilla-latte')
                ->where('product.onSale', true)
                ->where('seo.title', 'Vanilla Latte SEO')
                ->has('related')
            );
    }

    public function test_checkout_quote_coupon_and_gift_message(): void
    {
        [$company, $latte] = $this->seedStore();
        $slug = $company->store_slug;

        StorefrontCoupon::create([
            'company_id' => $company->id,
            'code' => 'SAVE10',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'redeemed_count' => 0,
        ]);

        $this->post("/s/{$slug}/cart", [
            'productId' => $latte->id,
            'quantity' => 2,
        ])->assertRedirect("/s/{$slug}/cart");

        $quote = $this->postJson("/s/{$slug}/checkout/quote", [
            'fulfillmentType' => 'pickup',
            'couponCode' => 'SAVE10',
            'tipAmount' => 1,
        ])->assertOk()
            ->assertJsonPath('couponValid', true)
            ->json();

        $this->assertEquals(1.0, (float) $quote['discountTotal']);

        $this->post("/s/{$slug}/checkout", [
            'customerName' => 'Ada Lovelace',
            'customerPhone' => '254711000111',
            'customerEmail' => 'ada@example.com',
            'fulfillmentType' => 'pickup',
            'orderNotes' => 'Leave at desk',
            'giftMessage' => 'Happy birthday!',
            'tipAmount' => 1,
            'couponCode' => 'SAVE10',
        ])->assertRedirect();

        $order = Order::where('company_id', $company->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('ada@example.com', $order->customer_email);
        $this->assertSame('Happy birthday!', $order->gift_message);
        $this->assertSame('Leave at desk', $order->order_notes);
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertEquals(1.0, (float) $order->discount_total);
        $this->assertEquals(1.0, (float) $order->tip_amount);
        $this->assertTrue(StorefrontEvent::where('company_id', $company->id)->where('event', 'purchase')->exists());
    }

    public function test_guest_order_tracking(): void
    {
        [$company, $latte] = $this->seedStore();
        $slug = $company->store_slug;

        $this->post("/s/{$slug}/cart", ['productId' => $latte->id, 'quantity' => 1]);
        $this->post("/s/{$slug}/checkout", [
            'customerName' => 'Tracker',
            'customerPhone' => '254722333444',
            'fulfillmentType' => 'pickup',
        ]);

        $order = Order::firstOrFail();

        $this->post("/s/{$slug}/track", [
            'phone' => '254722333444',
            'orderNumber' => $order->order_number,
        ])->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('store/track')
                ->where('notFound', false)
                ->where('order.orderNumber', $order->order_number)
            );

        $this->post("/s/{$slug}/track", [
            'phone' => '254700000000',
            'orderNumber' => $order->order_number,
        ])->assertOk()
            ->assertInertia(fn ($page) => $page->where('notFound', true)->where('order', null));
    }

    public function test_wishlist_toggle_and_whatsapp_cta(): void
    {
        [$company, $latte] = $this->seedStore();
        $slug = $company->store_slug;

        $this->get("/s/{$slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('company.whatsappUrl', fn ($url) => is_string($url) && str_contains($url, 'wa.me/254700999888'))
            );

        $this->postJson("/s/{$slug}/wishlist/toggle", ['productId' => $latte->id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('wishlist.0', (string) $latte->id);
    }

    public function test_approved_reviews_show_unapproved_hidden(): void
    {
        [$company, $latte] = $this->seedStore();

        ProductReview::create([
            'company_id' => $company->id,
            'product_id' => $latte->id,
            'author_name' => 'Hidden',
            'rating' => 2,
            'body' => 'meh',
            'is_approved' => false,
        ]);
        ProductReview::create([
            'company_id' => $company->id,
            'product_id' => $latte->id,
            'author_name' => 'Visible',
            'rating' => 5,
            'body' => 'great',
            'is_approved' => true,
        ]);

        $this->get("/s/{$company->store_slug}/p/{$latte->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('product.reviews', 1)
                ->where('product.reviews.0.authorName', 'Visible')
                ->where('product.averageRating', 5)
            );
    }

    public function test_sitemap_includes_storefront_urls(): void
    {
        [$company, $latte] = $this->seedStore();

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/s/'.$company->store_slug, $xml);
        $this->assertStringContainsString('/s/'.$company->store_slug.'/p/'.$latte->slug, $xml);
    }

    public function test_storefront_coupon_api_crud(): void
    {
        [$company] = $this->seedStore();
        \App\Models\Subscription::create([
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

        $this->postJson('/api/company/storefront-coupons', [
            'code' => 'WELCOME',
            'type' => 'fixed',
            'value' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('coupon.code', 'WELCOME');

        $this->getJson('/api/company/storefront-coupons')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_cart_quantity_update_cannot_exceed_stock(): void
    {
        [$company, $latte] = $this->seedStore();
        $slug = $company->store_slug;

        $this->post("/s/{$slug}/cart", [
            'productId' => $latte->id,
            'quantity' => 1,
        ])->assertRedirect("/s/{$slug}/cart");

        $this->from("/s/{$slug}/cart")
            ->post("/s/{$slug}/cart/update", [
                'key' => $latte->id.':0',
                'quantity' => 99,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('quantity');
    }

    public function test_custom_domain_redirects_to_storefront(): void
    {
        [$company] = $this->seedStore();
        $company->update([
            'custom_domain' => 'shop.classic.test',
            'custom_domain_verified_at' => now(),
        ]);

        $this->get('http://shop.classic.test/')
            ->assertRedirect('/s/'.$company->store_slug);

        $this->get('http://shop.classic.test/cart')
            ->assertRedirect('/s/'.$company->store_slug.'/cart');
    }

    public function test_abandoned_cart_job_is_dispatchable(): void
    {
        $this->assertTrue(class_exists(\App\Jobs\Storefront\ProcessAbandonedCartJob::class));
        (new \App\Jobs\Storefront\ProcessAbandonedCartJob)->handle(
            app(\App\Services\Storefront\AbandonedCartRecoveryService::class)
        );
        $this->assertTrue(true);
    }

    public function test_checkout_links_chat_and_sends_whatsapp_confirmation(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'graph.facebook.com/*' => \Illuminate\Support\Facades\Http::response(['messages' => [['id' => 'wamid.store.1']]], 200),
        ]);

        [$company, $latte] = $this->seedStore();
        $slug = $company->store_slug;

        \App\Models\CompanySetting::where('company_id', $company->id)->update([
            'storefront_whatsapp_order_notify' => true,
        ]);

        \App\Models\WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-store-bridge',
            'whatsapp_business_account_id' => 'waba-store-bridge',
            'access_token' => 'wa-token',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        $this->post("/s/{$slug}/cart", [
            'productId' => $latte->id,
            'quantity' => 1,
        ])->assertRedirect("/s/{$slug}/cart");

        $response = $this->post("/s/{$slug}/checkout", [
            'customerName' => 'Amina',
            'customerPhone' => '+254700111222',
            'customerEmail' => 'amina@test.local',
            'fulfillmentType' => 'pickup',
        ]);

        $order = Order::where('company_id', $company->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertNotNull($order->chat_id);
        $this->assertSame('254700111222', $order->chat->customer_phone);
        $this->assertSame('storefront', $order->source);

        $response->assertRedirect('/s/'.$slug.'/order/'.$order->pay_token);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $order->chat_id,
            'sender' => 'bot',
        ]);

        $message = \App\Models\Message::where('chat_id', $order->chat_id)->latest('id')->first();
        $this->assertNotNull($message);
        $this->assertStringContainsString($order->order_number, $message->content);
        $this->assertStringContainsString('/pay/', $message->content);
        $this->assertStringContainsString('/s/'.$slug.'/track', $message->content);
    }

    public function test_abandoned_cart_recovery_sends_enriched_whatsapp_and_creates_chat(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'graph.facebook.com/*' => \Illuminate\Support\Facades\Http::response(['messages' => [['id' => 'wamid.cart.1']]], 200),
        ]);

        [$company, $latte] = $this->seedStore();

        \App\Models\WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-abandoned',
            'whatsapp_business_account_id' => 'waba-abandoned',
            'access_token' => 'wa-token',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        $session = \App\Models\StorefrontSession::create([
            'company_id' => $company->id,
            'session_token' => 'tok-abandoned-1',
            'customer_name' => 'Brian',
            'customer_phone' => '254711333444',
            'cart' => [
                $latte->id.':0' => [
                    'product_id' => $latte->id,
                    'product_variant_id' => null,
                    'quantity' => 2,
                ],
            ],
            'last_activity_at' => now()->subHours(2),
            'abandoned_notified_at' => null,
        ]);

        $result = app(\App\Services\Storefront\AbandonedCartRecoveryService::class)->processDue(60);

        $this->assertSame(1, $result['sent']);
        $session->refresh();
        $this->assertNotNull($session->abandoned_notified_at);

        $chat = \App\Models\Chat::where('company_id', $company->id)
            ->where('customer_phone', '254711333444')
            ->first();
        $this->assertNotNull($chat);
        $this->assertDatabaseHas('messages', ['chat_id' => $chat->id, 'sender' => 'bot']);
        $msg = \App\Models\Message::where('chat_id', $chat->id)->latest('id')->first();
        $this->assertStringContainsString('2 item', $msg->content);
        $this->assertStringContainsString('/s/'.$company->store_slug.'/cart', $msg->content);
    }

    public function test_settings_expose_abandoned_cart_and_order_notify_toggles(): void
    {
        [$company] = $this->seedStore();
        \App\Models\Subscription::create([
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

        $this->putJson('/api/company/settings', [
            'abandonedCartRecoveryEnabled' => true,
            'storefrontWhatsappOrderNotify' => false,
            'abandonedCartTemplateName' => 'cart_recovery_v1',
        ])->assertOk();

        $show = $this->getJson('/api/company/settings');
        $show->assertOk()
            ->assertJsonPath('abandonedCartRecoveryEnabled', true)
            ->assertJsonPath('storefrontWhatsappOrderNotify', false)
            ->assertJsonPath('abandonedCartTemplateName', 'cart_recovery_v1');
    }

    public function test_product_page_whatsapp_prefill_includes_product_link(): void
    {
        [$company, $latte] = $this->seedStore();
        $slug = $company->store_slug;

        $response = $this->get("/s/{$slug}/p/{$latte->slug}");
        $response->assertOk();
        $page = $response->viewData('page');
        $props = is_array($page) ? ($page['props'] ?? []) : [];
        $wa = $props['company']['whatsappUrl'] ?? null;
        $this->assertIsString($wa);
        $this->assertStringContainsString('wa.me/', $wa);
        $decoded = urldecode($wa);
        $this->assertStringContainsString($latte->name, $decoded);
        $this->assertStringContainsString('/s/'.$slug.'/p/', $decoded);
    }
}
