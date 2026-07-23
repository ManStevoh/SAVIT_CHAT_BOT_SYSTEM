<?php

namespace App\Services\Cms;

use App\Models\BlogPost;
use App\Models\CmsPage;
use App\Models\LandingFaq;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CmsSeoService
{
    /**
     * @return array<string, mixed>|null
     */
    public function forSlug(string $slug): ?array
    {
        if (! Schema::hasTable('cms_pages')) {
            return $this->fallback($slug);
        }

        try {
            $page = CmsPage::where('slug', $slug)->where('is_published', true)->first();
        } catch (\Throwable) {
            return $this->fallback($slug);
        }

        if (! $page) {
            return $this->fallback($slug);
        }

        return $this->toPayload($page);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(CmsPage $page): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $path = $this->pathForSlug($page->slug);
        $canonical = $page->canonical_url
            ? $this->absoluteUrl($page->canonical_url)
            : $base.$path;

        $title = trim((string) ($page->meta_title ?: $page->title));
        $description = trim((string) ($page->meta_description ?? ''));
        $ogTitle = trim((string) ($page->og_title ?: $title));
        $ogDescription = trim((string) ($page->og_description ?: $description));
        $ogImage = $this->absoluteUrl($page->og_image) ?: $this->defaultOgImage();
        $siteName = (string) config('app.name', 'RelayIQ');

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter([
                [
                    '@type' => 'Organization',
                    '@id' => $base.'/#organization',
                    'name' => $siteName,
                    'url' => $base,
                    'logo' => $this->defaultOgImage(),
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $base.'/#website',
                    'name' => $siteName,
                    'url' => $base,
                    'publisher' => ['@id' => $base.'/#organization'],
                ],
                [
                    '@type' => 'WebPage',
                    '@id' => $canonical.'#webpage',
                    'url' => $canonical,
                    'name' => $title,
                    'description' => $description ?: null,
                    'isPartOf' => ['@id' => $base.'/#website'],
                    'about' => ['@id' => $base.'/#organization'],
                ],
                $this->softwareApplicationNode($base, $siteName, $description),
                $this->faqNode($page),
            ])),
        ];

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $page->robots ?: 'index, follow',
            'ogTitle' => $ogTitle,
            'ogDescription' => $ogDescription,
            'ogImage' => $ogImage,
            'ogType' => 'website',
            'ogUrl' => $canonical,
            'siteName' => $siteName,
            'twitterCard' => 'summary_large_image',
            'jsonLd' => $jsonLd,
        ];
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq: string, priority: string}>
     */
    public function sitemapEntries(): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $entries = [];

        if (Schema::hasTable('cms_pages')) {
            try {
                $pages = CmsPage::query()
                    ->where('is_published', true)
                    ->where('slug', '!=', 'global')
                    ->orderBy('id')
                    ->get();

                foreach ($pages as $page) {
                    $path = $this->pathForSlug($page->slug);
                    $entries[] = [
                        'loc' => $base.$path,
                        'lastmod' => optional($page->updated_at)?->toAtomString(),
                        'changefreq' => $page->slug === 'home' ? 'weekly' : 'monthly',
                        'priority' => $page->slug === 'home' ? '1.0' : '0.8',
                    ];
                }
            } catch (\Throwable) {
                // fall through with empty CMS entries
            }
        }

        $entries[] = [
            'loc' => $base.'/blog',
            'lastmod' => now()->toAtomString(),
            'changefreq' => 'weekly',
            'priority' => '0.7',
        ];

        if (Schema::hasTable('blog_posts')) {
            try {
                $latest = BlogPost::published()->orderByDesc('updated_at')->value('updated_at');
                if ($latest) {
                    $entries[array_key_last($entries)]['lastmod'] = optional($latest)->toAtomString() ?? now()->toAtomString();
                }

                foreach (BlogPost::published()->orderByDesc('published_at')->get() as $post) {
                    $entries[] = [
                        'loc' => $base.'/blog/'.$post->slug,
                        'lastmod' => optional($post->updated_at)?->toAtomString(),
                        'changefreq' => 'monthly',
                        'priority' => '0.6',
                    ];
                }
            } catch (\Throwable) {
                // ignore blog sitemap errors
            }
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    public function forBlogIndex(): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $canonical = $base.'/blog';
        $siteName = (string) config('app.name', 'RelayIQ');
        $title = 'Blog — '.$siteName;
        $description = 'Guides and updates on WhatsApp commerce, AI sales, and growing with '.$siteName.'.';

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $this->defaultOgImage(),
            'ogType' => 'website',
            'ogUrl' => $canonical,
            'siteName' => $siteName,
            'twitterCard' => 'summary_large_image',
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'Blog',
                'name' => $title,
                'url' => $canonical,
                'description' => $description,
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $siteName,
                    'url' => $base,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forBlogPost(string $slug): ?array
    {
        if (! Schema::hasTable('blog_posts')) {
            return null;
        }

        try {
            $post = BlogPost::published()->where('slug', $slug)->first();
        } catch (\Throwable) {
            return null;
        }

        if (! $post) {
            return null;
        }

        $base = rtrim((string) config('app.url'), '/');
        $canonical = $base.'/blog/'.$post->slug;
        $siteName = (string) config('app.name', 'RelayIQ');
        $title = trim((string) ($post->meta_title ?: $post->title));
        $description = trim((string) ($post->meta_description ?: $post->excerpt ?: ''));
        $ogImage = $post->absoluteImage($post->og_image ?: $post->cover_image) ?: $this->defaultOgImage();

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $ogImage,
            'ogType' => 'article',
            'ogUrl' => $canonical,
            'siteName' => $siteName,
            'twitterCard' => 'summary_large_image',
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                'headline' => $post->title,
                'description' => $description ?: null,
                'image' => $ogImage,
                'datePublished' => $post->published_at?->toAtomString(),
                'dateModified' => $post->updated_at?->toAtomString(),
                'mainEntityOfPage' => $canonical,
                'author' => [
                    '@type' => 'Organization',
                    'name' => $siteName,
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $siteName,
                    'url' => $base,
                    'logo' => $this->defaultOgImage(),
                ],
            ],
        ];
    }

    public function faviconUrl(): ?string
    {
        return asset('images/branding/relaysiq-favicon.png');
    }

    public function pathForSlug(string $slug): string
    {
        return match ($slug) {
            'home', 'global' => '/',
            default => '/'.$slug,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fallback(string $slug): ?array
    {
        $defaults = [
            'home' => [
                'title' => 'RelayIQ — AI WhatsApp Sales & Order Automation',
                'description' => 'Turn WhatsApp into your best sales channel. AI replies, order flows, and payments without leaving the chat.',
            ],
            'pricing' => [
                'title' => 'Pricing — RelayIQ',
                'description' => 'Simple plans for WhatsApp commerce teams. Start free and scale as you grow.',
            ],
            'about' => [
                'title' => 'About us — RelayIQ',
                'description' => 'Learn how RelayIQ helps businesses sell and support customers on WhatsApp.',
            ],
            'contact' => [
                'title' => 'Contact — RelayIQ',
                'description' => 'Get in touch with the RelayIQ team.',
            ],
            'privacy' => [
                'title' => 'Privacy Policy — RelayIQ',
                'description' => 'How RelayIQ collects, uses, and protects your data.',
            ],
            'terms' => [
                'title' => 'Terms of Service — RelayIQ',
                'description' => 'Terms governing use of the RelayIQ platform.',
            ],
        ];

        if (! isset($defaults[$slug])) {
            return null;
        }

        $base = rtrim((string) config('app.url'), '/');
        $path = $this->pathForSlug($slug);
        $canonical = $base.$path;
        $siteName = (string) config('app.name', 'RelayIQ');

        return [
            'title' => $defaults[$slug]['title'],
            'description' => $defaults[$slug]['description'],
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'ogTitle' => $defaults[$slug]['title'],
            'ogDescription' => $defaults[$slug]['description'],
            'ogImage' => $this->defaultOgImage(),
            'ogType' => 'website',
            'ogUrl' => $canonical,
            'siteName' => $siteName,
            'twitterCard' => 'summary_large_image',
            'jsonLd' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function softwareApplicationNode(string $base, string $siteName, string $description): ?array
    {
        return [
            '@type' => 'SoftwareApplication',
            'name' => $siteName,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'url' => $base,
            'description' => $description ?: null,
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'USD',
                'description' => 'Free trial available',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function faqNode(CmsPage $page): ?array
    {
        if (! Schema::hasTable('landing_faqs')) {
            return null;
        }

        try {
            $page->loadMissing('sections');
        } catch (\Throwable) {
            return null;
        }

        $faqEnabled = $page->sections->contains(
            fn ($s) => $s->section_key === 'faq' && $s->is_enabled
        );

        if (! $faqEnabled) {
            return null;
        }

        try {
            $faqs = LandingFaq::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } catch (\Throwable) {
            return null;
        }

        if ($faqs->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(fn ($f) => [
                '@type' => 'Question',
                'name' => $f->question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags((string) $f->answer),
                ],
            ])->values()->all(),
        ];
    }

    private function absoluteUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return rtrim((string) config('app.url'), '/').$url;
        }

        if (Storage::disk('public')->exists($url)) {
            return asset('storage/'.$url);
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    private function defaultOgImage(): ?string
    {
        if (! Schema::hasTable('platform_settings')) {
            return null;
        }

        try {
            $settings = PlatformSetting::query()->first();
        } catch (\Throwable) {
            return null;
        }

        if ($settings && ! empty($settings->app_logo) && Storage::disk('public')->exists($settings->app_logo)) {
            return asset('storage/'.$settings->app_logo);
        }

        return null;
    }
}
