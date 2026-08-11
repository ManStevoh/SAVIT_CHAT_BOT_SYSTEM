<?php

namespace App\Services\Cms;

use App\Models\CmsPage;
use App\Models\LandingFaq;
use App\Models\PlatformSetting;
use App\Models\Testimonial;
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
            'sections' => $page->sections->map(fn ($s) => [
                'key' => $s->section_key,
                'label' => $s->label,
                'isEnabled' => (bool) $s->is_enabled,
                'sortOrder' => (int) $s->sort_order,
                'content' => $s->content ?? [],
            ])->values()->all(),
            ...$extras,
        ];
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
