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
}
