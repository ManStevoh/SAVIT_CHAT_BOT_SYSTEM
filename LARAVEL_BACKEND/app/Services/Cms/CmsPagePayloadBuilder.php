<?php

namespace App\Services\Cms;

use App\Models\CmsPage;
use App\Models\LandingFaq;
use App\Models\PlatformSetting;
use App\Models\Testimonial;
use App\Support\BrandSocial;
use App\Support\FeaturesPageCopy;
use App\Support\HomeSeoCopy;
use App\Support\PublicMarketingPages;
use App\Support\SeoLandingCatalog;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CmsPagePayloadBuilder
{
    /**
     * Public CMS page payload (same shape as GET /api/cms/pages/{slug}).
     *
     * @return array<string, mixed>|null
     */
    public function forSlug(string $slug): ?array
    {
        if (! Schema::hasTable('cms_pages')) {
            return $slug === 'features' ? FeaturesPageCopy::payload() : SeoLandingCatalog::payload($slug);
        }

        try {
            $page = CmsPage::where('slug', $slug)->where('is_published', true)->first();
        } catch (\Throwable) {
            return $slug === 'features' ? FeaturesPageCopy::payload() : SeoLandingCatalog::payload($slug);
        }

        if (! $page) {
            return $slug === 'features' ? FeaturesPageCopy::payload() : SeoLandingCatalog::payload($slug);
        }

        $payload = $this->toArray($page);
        $resolved = $this->hasRenderableBody($payload)
            ? $this->enrichFromCatalog($slug, $payload)
            : (SeoLandingCatalog::payload($slug) ?? $payload);

        if ($slug === 'features' && FeaturesPageCopy::shouldReplace($resolved ?? [])) {
            return FeaturesPageCopy::apply($resolved);
        }

        if ($slug === 'features' && is_array($resolved)) {
            $resolved['faqs'] = FeaturesPageCopy::faqs();
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasRenderableBody(array $payload): bool
    {
        foreach ($payload['sections'] ?? [] as $section) {
            if (! ($section['isEnabled'] ?? false)) {
                continue;
            }
            $content = is_array($section['content'] ?? null) ? $section['content'] : [];
            $key = (string) ($section['key'] ?? '');
            if ($key === 'prose' && filled($content['html'] ?? $content['body'] ?? '')) {
                return true;
            }
            if ($key === 'hero' && filled($content['title'] ?? $content['headline'] ?? '')) {
                return true;
            }
            if (in_array($key, ['capabilities', 'solution_pillars', 'industries', 'feature_catalog'], true)
                && ! empty($content['items'] ?? $content['groups'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enrichFromCatalog(string $slug, array $payload): array
    {
        if (empty($payload['faqs']) && SeoLandingCatalog::faqs($slug) !== []) {
            $payload['faqs'] = SeoLandingCatalog::faqs($slug);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(CmsPage $page): array
    {
        $page->loadMissing('sections');

        $enabledKeys = $page->sections->where('is_enabled', true)->pluck('section_key')->all();
        $extras = [];

        if (in_array('testimonials', $enabledKeys, true) && Schema::hasTable('testimonials')) {
            try {
                $extras['testimonials'] = Testimonial::where('is_active', true)
                    ->orderBy('sort_order')->orderBy('id')
                    ->get()
                    ->map(fn ($t) => [
                        'id' => (string) $t->id,
                        'name' => $t->name,
                        'role' => $t->role ?? '',
                        'content' => $t->content,
                        'rating' => (int) $t->rating,
                    ])->values()->all();
            } catch (\Throwable) {
                // ignore
            }
        }

        if (in_array('faq', $enabledKeys, true) && Schema::hasTable('landing_faqs')) {
            try {
                $extras['faqs'] = LandingFaq::where('is_active', true)
                    ->orderBy('sort_order')->orderBy('id')
                    ->get()
                    ->map(fn ($f) => [
                        'id' => (string) $f->id,
                        'question' => $f->question,
                        'answer' => $f->answer,
                    ])->values()->all();
            } catch (\Throwable) {
                // ignore
            }
        }

        if (in_array('trusted_companies', $enabledKeys, true) && Schema::hasTable('platform_settings')) {
            try {
                $settings = PlatformSetting::first();
                $fromSettings = $settings?->landing_trusted_companies ?? [];
                $extras['trustedCompanies'] = is_array($fromSettings) ? $fromSettings : [];
            } catch (\Throwable) {
                $extras['trustedCompanies'] = [];
            }
        }

        $pageMeta = [
            'slug' => $page->slug,
            'title' => $page->title,
            'metaTitle' => $page->meta_title,
            'metaDescription' => $page->meta_description,
            'ogImage' => $this->resolveImageUrl($page->og_image),
            'ogTitle' => $page->og_title,
            'ogDescription' => $page->og_description,
            'canonicalUrl' => $page->canonical_url,
            'robots' => $page->robots,
        ];
        if ($page->slug === 'home' && HomeSeoCopy::shouldReplaceMeta(
            (string) ($pageMeta['metaTitle'] ?? ''),
            (string) ($pageMeta['metaDescription'] ?? '')
        )) {
            $pageMeta['metaTitle'] = HomeSeoCopy::title();
            $pageMeta['metaDescription'] = HomeSeoCopy::description();
        }
        if ($page->slug === 'home' && HomeSeoCopy::shouldReplaceMeta(
            (string) ($pageMeta['ogTitle'] ?? ''),
            (string) ($pageMeta['ogDescription'] ?? '')
        )) {
            $pageMeta['ogTitle'] = HomeSeoCopy::title();
            $pageMeta['ogDescription'] = HomeSeoCopy::description();
        }

        return [
            'page' => $pageMeta,
            'sections' => $page->sections->map(function ($s) use ($page) {
                $content = $s->content ?? [];
                if ($page->slug === 'home' && $s->section_key === 'hero' && is_array($content)) {
                    $content = HomeSeoCopy::applyHero($content);
                }
                if ($page->slug === 'home' && $s->section_key === 'cta' && is_array($content)) {
                    $ctaTitle = mb_strtolower(trim((string) ($content['title'] ?? '')));
                    if ($ctaTitle === '' || str_contains($ctaTitle, 'ready to sell on whatsapp')) {
                        $content['title'] = 'Start free this week — shop, bookings, tables, and WhatsApp AI';
                        $content['description'] = 'One free Starter account: a web storefront, appointment bookings, dine-in QR, and WhatsApp selling when you connect your number. No credit card. Upgrade only when you need more.';
                        $content['ctaText'] = $content['ctaText'] ?: 'Get started free';
                        $content['ctaHref'] = $content['ctaHref'] ?: '/register';
                        $content['secondaryCtaText'] = $content['secondaryCtaText'] ?: 'See pricing';
                        $content['secondaryCtaHref'] = $content['secondaryCtaHref'] ?: '/pricing';
                    }
                    $content['imageUrl'] = '/images/lando/lando-cta.jpg';
                    $content['imageAlt'] = 'Kenyan shop owner managing WhatsApp orders from her phone';
                    $content['showImage'] = true;
                }
                if ($page->slug === 'global' && $s->section_key === 'auth_shell' && is_array($content)) {
                    $authImage = (string) ($content['imageUrl'] ?? '');
                    if ($authImage === '' || str_contains($authImage, 'lando-intro.png') || str_contains($authImage, 'lando-hero.png')) {
                        $content['imageUrl'] = '/images/lando/lando-auth.jpg';
                        $content['imageAlt'] = 'Kenyan cafe owner checking WhatsApp orders on his phone';
                    }
                }
                if ($page->slug === 'about' && $s->section_key === 'hero' && is_array($content)) {
                    $aboutImage = (string) ($content['imageUrl'] ?? '');
                    if ($aboutImage === '' || str_contains($aboutImage, 'lando-about-team.png')) {
                        $content['imageUrl'] = '/images/lando/lando-about-team.jpg';
                        $content['imageAlt'] = 'RelayIQ teammates in Nairobi reviewing the product together';
                    }
                }
                if ($page->slug === 'features' && $s->section_key === 'feature_2' && is_array($content)) {
                    $content['imageUrl'] = '/images/lando/lando-feature-storefront.jpg?v=man1';
                    $content['imageAlt'] = 'Customer browsing a shop on his phone outside a Nairobi boutique';
                }
                if ($page->slug === 'contact' && $s->section_key === 'hero' && is_array($content)) {
                    $contactImage = (string) ($content['imageUrl'] ?? '');
                    if ($contactImage === '' || str_contains($contactImage, 'lando-contact.png')) {
                        $content['imageUrl'] = '/images/lando/lando-contact.jpg';
                        $content['imageAlt'] = 'RelayIQ teammate ready to help from Nairobi';
                    }
                }
                if ($s->section_key === 'navbar' && is_array($content)) {
                    $content['links'] = PublicMarketingPages::filterLinks($content['links'] ?? []);
                }
                if ($s->section_key === 'footer' && is_array($content)) {
                    $content['socialLinks'] = $this->publicSocialLinks($content['socialLinks'] ?? []);
                    $content['navLinks'] = PublicMarketingPages::filterLinks(
                        $this->mergeSeoFooterLinks($content['navLinks'] ?? [])
                    );
                }
                if (is_array($content)) {
                    $content = $this->bustBrandedIllustrationCache($content);
                }

                return [
                    'key' => $s->section_key,
                    'label' => $s->label,
                    'isEnabled' => (bool) $s->is_enabled,
                    'sortOrder' => (int) $s->sort_order,
                    'content' => $content,
                ];
            })->values()->all(),
            ...$extras,
        ];
    }

    /**
     * @param  mixed  $cmsLinks
     * @return list<array{label: string, href: string}>
     */
    private function mergeSeoFooterLinks(mixed $cmsLinks): array
    {
        $links = [];
        $hrefs = [];
        if (is_array($cmsLinks)) {
            foreach ($cmsLinks as $link) {
                if (! is_array($link)) {
                    continue;
                }
                $href = trim((string) ($link['href'] ?? ''));
                $label = trim((string) ($link['label'] ?? ''));
                if ($href === '' || $label === '') {
                    continue;
                }
                $links[] = ['label' => $label, 'href' => $href];
                $hrefs[] = rtrim($href, '/');
            }
        }
        foreach (SeoLandingCatalog::footerLinks() as $extra) {
            if (! in_array(rtrim($extra['href'], '/'), $hrefs, true)) {
                $links[] = $extra;
                $hrefs[] = rtrim($extra['href'], '/');
            }
        }

        return $links;
    }

    /**
     * Official Facebook + Instagram always win over CMS "#" placeholders.
     *
     * @param  mixed  $cmsLinks
     * @return list<array{label: string, href: string}>
     */
    private function publicSocialLinks(mixed $cmsLinks): array
    {
        $official = BrandSocial::links();
        $officialLabels = array_map(
            static fn (array $link): string => strtolower($link['label']),
            $official
        );

        $extra = [];
        if (is_array($cmsLinks)) {
            foreach ($cmsLinks as $link) {
                if (! is_array($link)) {
                    continue;
                }
                $label = strtolower(trim((string) ($link['label'] ?? '')));
                $href = trim((string) ($link['href'] ?? $link['url'] ?? ''));
                if (in_array($label, $officialLabels, true)) {
                    continue;
                }
                if ($this->isPublicExternalUrl($href)) {
                    $extra[] = [
                        'label' => trim((string) ($link['label'] ?? 'Social')),
                        'href' => $href,
                    ];
                }
            }
        }

        return array_values([...$official, ...$extra]);
    }

    private function isPublicExternalUrl(string $href): bool
    {
        if (! preg_match('#^https?://#i', $href)) {
            return false;
        }

        $host = parse_url($href, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return strtolower($host) !== strtolower((string) $appHost);
    }

    private function resolveImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        if (str_starts_with($path, 'http') || str_starts_with($path, '/')) {
            return $path;
        }
        if (Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return asset($path);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function bustBrandedIllustrationCache(array $content): array
    {
        $url = (string) ($content['imageUrl'] ?? '');
        if ($url === '' || str_contains($url, '?')) {
            return $content;
        }

        foreach (['lando-hero.png', 'lando-storefront.png', 'lando-bookings.png', 'lando-dinein.png'] as $file) {
            if (str_contains($url, $file)) {
                $content['imageUrl'] = $url.'?v=brand2';
                break;
            }
        }

        return $content;
    }
}
