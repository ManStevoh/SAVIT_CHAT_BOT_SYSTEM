<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CmsSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_page_html_includes_seo_meta_and_og_tags(): void
    {
        CmsPage::create([
            'slug' => 'home',
            'title' => 'Home',
            'meta_title' => 'RelayIQ SEO Test Title',
            'meta_description' => 'RelayIQ SEO test description for crawlers.',
            'og_image' => 'https://cdn.example.com/og-home.png',
            'og_title' => 'Share title',
            'og_description' => 'Share description',
            'robots' => 'index, follow',
            'is_published' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('RelayIQ SEO Test Title', false);
        $response->assertSee('RelayIQ SEO test description for crawlers.', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('Share title', false);
        $response->assertSee('https://cdn.example.com/og-home.png', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('rel="canonical"', false);
    }

    public function test_sitemap_lists_published_cms_pages(): void
    {
        CmsPage::create([
            'slug' => 'about',
            'title' => 'About',
            'meta_title' => 'About',
            'meta_description' => 'About us',
            'is_published' => true,
        ]);
        CmsPage::create([
            'slug' => 'draft',
            'title' => 'Draft',
            'is_published' => false,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('/about', false);
        $response->assertDontSee('/draft', false);
    }

    public function test_robots_txt_includes_sitemap_and_disallows_private_areas(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee('Disallow: /dashboard', false);
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Sitemap:', false);
        $response->assertSee('/sitemap.xml', false);
    }

    public function test_super_admin_can_update_seo_image_and_copy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $page = CmsPage::create([
            'slug' => 'pricing',
            'title' => 'Pricing',
            'meta_title' => 'Old',
            'meta_description' => 'Old desc',
            'is_published' => true,
        ]);

        $response = $this->putJson('/api/admin/cms/pages/pricing', [
            'metaTitle' => 'Pricing — RelayIQ',
            'metaDescription' => 'Plans for WhatsApp commerce.',
            'ogImage' => 'https://cdn.example.com/pricing-og.jpg',
            'ogTitle' => 'Pricing share title',
            'ogDescription' => 'Pricing share description',
            'canonicalUrl' => 'https://ai.essemdigital.com/pricing',
            'robots' => 'index, follow',
            'isPublished' => true,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $page->refresh();
        $this->assertSame('Pricing — RelayIQ', $page->meta_title);
        $this->assertSame('https://cdn.example.com/pricing-og.jpg', $page->og_image);
        $this->assertSame('Pricing share title', $page->og_title);
        $this->assertSame('https://ai.essemdigital.com/pricing', $page->canonical_url);
    }
}
