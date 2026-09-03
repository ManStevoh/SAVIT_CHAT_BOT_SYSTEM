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

    public function test_marketing_json_ld_does_not_claim_social_profile_urls(): void
    {
        CmsPage::create([
            'slug' => 'home',
            'title' => 'Home',
            'meta_title' => 'RelayIQ | AI Sales Agent for WhatsApp',
            'meta_description' => 'Turn WhatsApp conversations into sales with RelayIQ.',
            'is_published' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('sameAs', false);
        $response->assertDontSee('rel="me"', false);
        $response->assertSee('Relay IQ', false);
        $response->assertSee('RelayIQ.app', false);
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
        $response->assertSee('Disallow: /login', false);
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
            'canonicalUrl' => 'https://relayiq.app/pricing',
            'robots' => 'index, follow',
            'isPublished' => true,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $page->refresh();
        $this->assertSame('Pricing — RelayIQ', $page->meta_title);
        $this->assertSame('https://cdn.example.com/pricing-og.jpg', $page->og_image);
        $this->assertSame('Pricing share title', $page->og_title);
        $this->assertSame('https://relayiq.app/pricing', $page->canonical_url);
    }

    public function test_admin_can_toggle_footer_mobile_app_section_via_cms(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $page = CmsPage::create([
            'slug' => 'global',
            'title' => 'Global',
            'is_published' => true,
        ]);
        \App\Models\CmsSection::create([
            'cms_page_id' => $page->id,
            'section_key' => 'footer',
            'label' => 'Footer',
            'is_enabled' => true,
            'sort_order' => 1,
            'content' => [
                'copyright' => '© Test',
                'navLinks' => [],
                'socialLinks' => [],
                'legalLinks' => [],
                'showMobileApp' => false,
            ],
        ]);

        $response = $this->putJson('/api/admin/cms/pages/global/sections/footer', [
            'content' => [
                'copyright' => '© Test',
                'navLinks' => [],
                'socialLinks' => [],
                'legalLinks' => [],
                'showMobileApp' => true,
                'mobileAppTitle' => 'Get the mobile app',
                'mobileAppDescription' => 'Chats on the go',
                'playStoreUrl' => 'https://play.google.com/store/apps/details?id=com.example',
                'appStoreUrl' => 'https://apps.apple.com/app/id123',
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $public = $this->getJson('/api/cms/pages/global');
        $public->assertOk();
        $footer = collect($public->json('sections'))->firstWhere('key', 'footer');
        $this->assertNotNull($footer);
        $this->assertTrue((bool) ($footer['content']['showMobileApp'] ?? false));
        $this->assertSame(
            'https://play.google.com/store/apps/details?id=com.example',
            $footer['content']['playStoreUrl'] ?? null
        );
    }

    public function test_public_footer_replaces_placeholder_social_links(): void
    {
        $page = CmsPage::create([
            'slug' => 'global',
            'title' => 'Global',
            'is_published' => true,
        ]);
        \App\Models\CmsSection::create([
            'cms_page_id' => $page->id,
            'section_key' => 'footer',
            'label' => 'Footer',
            'is_enabled' => true,
            'sort_order' => 1,
            'content' => [
                'socialLinks' => [
                    ['label' => 'Facebook', 'href' => '#'],
                    ['label' => 'Instagram', 'href' => '#'],
                    ['label' => 'Twitter', 'href' => '#'],
                    ['label' => 'Linkedin', 'href' => '#'],
                ],
            ],
        ]);

        $response = $this->getJson('/api/cms/global');
        $response->assertOk();
        $footer = collect($response->json('sections'))->firstWhere('key', 'footer');
        $links = $footer['content']['socialLinks'] ?? [];
        $hrefs = array_column($links, 'href');
        $labels = array_column($links, 'label');

        $this->assertContains('https://www.instagram.com/relayiq.app', $hrefs);
        $this->assertContains('https://www.facebook.com/share/1KxpxJ2VtK/', $hrefs);
        $this->assertNotContains('#', $hrefs);
        $this->assertNotContains('Twitter', $labels);
        $this->assertNotContains('Linkedin', $labels);
    }

    public function test_cms_image_upload_accepts_file_within_limit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $file = \Illuminate\Http\UploadedFile::fake()->image('hero.jpg', 800, 600);

        $response = $this->postJson('/api/admin/cms/upload-image', [
            'image' => $file,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNotEmpty($response->json('url'));
    }

    public function test_cms_image_upload_rejects_oversized_file_with_clear_message(): void
    {
        config(['cms.upload_max_kb' => 100]);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $file = \Illuminate\Http\UploadedFile::fake()->image('huge.jpg')->size(200);

        $response = $this->postJson('/api/admin/cms/upload-image', [
            'image' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image']);
    }
}
