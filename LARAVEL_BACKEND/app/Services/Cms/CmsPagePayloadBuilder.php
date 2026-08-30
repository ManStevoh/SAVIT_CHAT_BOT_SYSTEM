<?php

namespace App\Services\Cms;

use App\Models\CmsPage;
use App\Models\LandingFaq;
use App\Models\PlatformSetting;
use App\Models\Testimonial;
use App\Support\BrandSocial;
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
            return null;
        }

        try {
            $page = CmsPage::where('slug', $slug)->where('is_published', true)->first();
        } catch (\Throwable) {
            return null;
        }

        if (! $page) {
            return null;
        }

        return $this->toArray($page);
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

        return [
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'metaTitle' => $page->meta_title,
                'metaDescription' => $page->meta_description,
                'ogImage' => $this->resolveImageUrl($page->og_image),
                'ogTitle' => $page->og_title,
                'ogDescription' => $page->og_description,
                'canonicalUrl' => $page->canonical_url,
                'robots' => $page->robots,
            ],
            'sections' => $page->sections->map(function ($s) {
                $content = $s->content ?? [];
                if ($s->section_key === 'footer' && is_array($content)) {
                    $content['socialLinks'] = $this->publicSocialLinks($content['socialLinks'] ?? []);
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
}
