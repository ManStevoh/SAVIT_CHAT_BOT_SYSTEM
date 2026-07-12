<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\LandingFaq;
use App\Models\PlatformSetting;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CmsPageController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $page = CmsPage::where('slug', $slug)->where('is_published', true)->first();

        if (! $page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $page->load('sections');

        $enabledKeys = $page->sections->where('is_enabled', true)->pluck('section_key')->all();

        $extras = [];

        if (in_array('testimonials', $enabledKeys, true)) {
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
        }

        if (in_array('faq', $enabledKeys, true)) {
            $extras['faqs'] = LandingFaq::where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')
                ->get()
                ->map(fn ($f) => [
                    'id' => (string) $f->id,
                    'question' => $f->question,
                    'answer' => $f->answer,
                ])->values()->all();
        }

        if (in_array('trusted_companies', $enabledKeys, true)) {
            $settings = PlatformSetting::first();
            $fromSettings = $settings?->landing_trusted_companies ?? [];
            $extras['trustedCompanies'] = is_array($fromSettings) ? $fromSettings : [];
        }

        return response()->json([
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'metaTitle' => $page->meta_title,
                'metaDescription' => $page->meta_description,
            ],
            'sections' => $page->sections->map(fn ($s) => [
                'key' => $s->section_key,
                'label' => $s->label,
                'isEnabled' => (bool) $s->is_enabled,
                'sortOrder' => (int) $s->sort_order,
                'content' => $s->content ?? [],
            ])->values()->all(),
            ...$extras,
        ]);
    }

    public function global(): JsonResponse
    {
        return $this->show('global');
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
            return asset('storage/' . $path);
        }

        return asset($path);
    }
}
