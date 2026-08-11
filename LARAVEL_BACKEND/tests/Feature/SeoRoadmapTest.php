<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CmsPage;
use App\Models\CmsSection;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeoRoadmapTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Shop',
            'email' => 'acme'.rand(1000, 9999).'@test.local',
            'status' => 'active',
            'store_slug' => 'acme',
            'storefront_enabled' => true,
        ], $overrides));
    }

    public function test_marketing_page_includes_cms_body_in_inertia_props(): void
    {
        $page = CmsPage::create([
            'slug' => 'home',
            'title' => 'Home',
            'meta_title' => 'Home SEO Title',
            'meta_description' => 'Home SEO description',
            'is_published' => true,
        ]);
        CmsSection::create([
            'cms_page_id' => $page->id,
            'section_key' => 'hero',
            'label' => 'Hero',
            'is_enabled' => true,
            'sort_order' => 1,
            'content' => ['headline' => 'Sell on WhatsApp', 'subhead' => 'AI commerce OS'],
        ]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Home SEO Title', false);
        $response->assertSee('Sell on WhatsApp', false);
        $response->assertSee('BreadcrumbList', false);
    }

    public function test_login_is_noindex_and_robots_disallows_auth(): void
    {
        $response = $this->get('/login');
        $response->assertOk();
        $response->assertSee('noindex', false);

        $robots = $this->get('/robots.txt');
        $robots->assertOk();
        $robots->assertSee('Disallow: /login', false);
        $robots->assertSee('Disallow: /pay/', false);
        $robots->assertSee('Disallow: /whatsapp-debug-log', false);
    }

    public function test_storefront_product_includes_product_json_ld_and_og_image(): void
    {
        $this->makeCompany([
            'storefront_theme' => [
                'seo_title' => 'Acme Shop Online',
                'seo_description' => 'Fresh products from Acme with fast delivery.',
            ],
        ]);

        Product::create([
            'company_id' => Company::where('store_slug', 'acme')->value('id'),
            'name' => 'Blue Mug',
            'slug' => 'blue-mug',
            'description' => 'A ceramic mug',
            'meta_title' => 'Blue Mug — Buy Online',
            'meta_description' => 'Buy the Blue Mug from Acme Shop.',
            'price' => 19.99,
            'stock' => 5,
            'status' => 'active',
            'image' => 'products/1/mug.jpg',
        ]);

        $catalog = $this->get('/s/acme');
        $catalog->assertOk();
        $catalog->assertSee('Acme Shop Online', false);
        $catalog->assertSee('Fresh products from Acme', false);
        $catalog->assertSee('OnlineStore', false);

        $pdp = $this->get('/s/acme/p/blue-mug');
        $pdp->assertOk();
        $pdp->assertSee('Blue Mug — Buy Online', false);
        $pdp->assertSee('"@type":"Product"', false);
        $pdp->assertSee('og:image', false);
        $pdp->assertSee('application/ld+json', false);
    }

    public function test_cart_is_noindex(): void
    {
        $this->makeCompany();

        $response = $this->get('/s/acme/cart');
        $response->assertOk();
        $response->assertSee('noindex', false);
    }

    public function test_software_application_offers_use_plan_prices(): void
    {
        Plan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price_display' => '$29',
            'price_amount' => 29,
            'description' => 'Starter plan',
            'features' => [],
            'entitlements' => [],
            'popular' => false,
            'cta' => 'Start',
            'sort_order' => 1,
            'is_free' => false,
            'has_trial' => true,
            'trial_days' => 14,
        ]);

        CmsPage::create([
            'slug' => 'pricing',
            'title' => 'Pricing',
            'meta_title' => 'Pricing — RelayIQ',
            'meta_description' => 'Plans',
            'is_published' => true,
        ]);

        $response = $this->get('/pricing');
        $response->assertOk();
        $response->assertSee('SoftwareApplication', false);
        $response->assertSee('"price":"29', false);
    }

    public function test_merchant_can_save_product_meta_fields(): void
    {
        $company = $this->makeCompany(['store_slug' => 'meta-shop']);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        $user = User::factory()->create([
            'role' => 'company_owner',
            'company_id' => $company->id,
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Hat',
            'description' => 'Nice hat',
            'price' => 10,
            'stock' => 3,
            'status' => 'active',
        ]);

        $response = $this->putJson('/api/company/products/'.$product->id, [
            'name' => 'Hat',
            'description' => 'Nice hat',
            'metaTitle' => 'Buy Hat Online',
            'metaDescription' => 'Premium hat shipping fast',
            'slug' => 'premium-hat',
            'price' => 10,
            'stock' => 3,
            'category' => 'Apparel',
            'status' => 'active',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $product->refresh();
        $this->assertSame('Buy Hat Online', $product->meta_title);
        $this->assertSame('Premium hat shipping fast', $product->meta_description);
        $this->assertSame('premium-hat', $product->slug);
    }

    public function test_custom_domain_serves_storefront_without_redirect(): void
    {
        $this->makeCompany([
            'custom_domain' => 'shop.acme.test',
            'custom_domain_verified_at' => now(),
        ]);

        $response = $this->call(
            'GET',
            'http://shop.acme.test/',
            server: ['HTTP_HOST' => 'shop.acme.test']
        );

        $this->assertNotEquals(302, $response->getStatusCode());
    }

    public function test_sitemap_section_routes_exist(): void
    {
        $this->get('/sitemap-pages.xml')->assertOk();
        $this->get('/sitemap-blog.xml')->assertOk();
        $this->get('/sitemap-stores.xml')->assertOk();
    }
}
