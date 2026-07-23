<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlogSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_index_and_post_include_seo_and_appear_in_sitemap(): void
    {
        BlogPost::create([
            'title' => 'WhatsApp AI Guide',
            'slug' => 'whatsapp-ai-guide',
            'excerpt' => 'Learn how to sell on WhatsApp.',
            'body' => '<p>Hello world</p>',
            'meta_title' => 'WhatsApp AI Guide — RelayIQ',
            'meta_description' => 'A practical guide to WhatsApp AI sales.',
            'og_image' => 'https://cdn.example.com/blog-og.png',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $index = $this->get('/blog');
        $index->assertOk();
        $index->assertSee('Blog —', false);
        $index->assertSee('application/ld+json', false);

        $show = $this->get('/blog/whatsapp-ai-guide');
        $show->assertOk();
        $show->assertSee('WhatsApp AI Guide — RelayIQ', false);
        $show->assertSee('https://cdn.example.com/blog-og.png', false);
        $show->assertSee('BlogPosting', false);

        $sitemap = $this->get('/sitemap.xml');
        $sitemap->assertOk();
        $sitemap->assertSee('/blog', false);
        $sitemap->assertSee('/blog/whatsapp-ai-guide', false);
    }

    public function test_draft_posts_are_hidden_from_public_and_sitemap(): void
    {
        BlogPost::create([
            'title' => 'Draft',
            'slug' => 'draft-post',
            'body' => '<p>Secret</p>',
            'is_published' => false,
        ]);

        $this->get('/blog/draft-post')->assertNotFound();
        $this->getJson('/api/blog/posts/draft-post')->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/blog/draft-post', false);
    }

    public function test_admin_can_create_blog_post_with_seo_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/blog-posts', [
            'title' => 'New Post',
            'body' => '<p>Content</p>',
            'excerpt' => 'Short',
            'metaTitle' => 'New Post SEO',
            'metaDescription' => 'SEO description',
            'coverImage' => 'https://cdn.example.com/cover.jpg',
            'ogImage' => 'https://cdn.example.com/og.jpg',
            'isPublished' => true,
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('blog_posts', [
            'title' => 'New Post',
            'slug' => 'new-post',
            'meta_title' => 'New Post SEO',
            'is_published' => 1,
        ]);
    }

    public function test_favicon_is_present_in_html(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('rel="icon"', false);
        $response->assertSee('/images/branding/relaysiq-favicon.png', false);
    }
}
