<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\CmsSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CmsAdminController extends Controller
{
    public function pages(): JsonResponse
    {
        $pages = CmsPage::orderBy('id')->get()->map(fn (CmsPage $p) => [
            'id' => (string) $p->id,
            'slug' => $p->slug,
            'title' => $p->title,
            'metaTitle' => $p->meta_title,
            'metaDescription' => $p->meta_description,
            'ogImage' => $p->og_image,
            'ogTitle' => $p->og_title,
            'ogDescription' => $p->og_description,
            'canonicalUrl' => $p->canonical_url,
            'robots' => $p->robots,
            'isPublished' => (bool) $p->is_published,
        ]);

        return response()->json($pages->values()->all());
    }

    public function showPage(string $slug): JsonResponse
    {
        $page = CmsPage::where('slug', $slug)->with('sections')->first();
        if (! $page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        return response()->json([
            'page' => [
                'id' => (string) $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'metaTitle' => $page->meta_title,
                'metaDescription' => $page->meta_description,
                'ogImage' => $page->og_image,
                'ogTitle' => $page->og_title,
                'ogDescription' => $page->og_description,
                'canonicalUrl' => $page->canonical_url,
                'robots' => $page->robots,
                'isPublished' => (bool) $page->is_published,
            ],
            'sections' => $page->sections->map(fn (CmsSection $s) => [
                'id' => (string) $s->id,
                'key' => $s->section_key,
                'label' => $s->label,
                'isEnabled' => (bool) $s->is_enabled,
                'sortOrder' => (int) $s->sort_order,
                'content' => $s->content ?? [],
            ])->values()->all(),
        ]);
    }

    public function updatePage(Request $request, string $slug): JsonResponse
    {
        $page = CmsPage::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'metaTitle' => 'nullable|string|max:255',
            'metaDescription' => 'nullable|string|max:2000',
            'ogImage' => 'nullable|string|max:2048',
            'ogTitle' => 'nullable|string|max:255',
            'ogDescription' => 'nullable|string|max:2000',
            'canonicalUrl' => 'nullable|string|max:2048',
            'robots' => 'nullable|string|max:64',
            'isPublished' => 'sometimes|boolean',
        ]);

        $map = [
            'title' => 'title',
            'metaTitle' => 'meta_title',
            'metaDescription' => 'meta_description',
            'ogImage' => 'og_image',
            'ogTitle' => 'og_title',
            'ogDescription' => 'og_description',
            'canonicalUrl' => 'canonical_url',
            'robots' => 'robots',
        ];

        foreach ($map as $input => $column) {
            if (array_key_exists($input, $validated)) {
                $page->{$column} = $validated[$input];
            }
        }
        if (array_key_exists('isPublished', $validated)) {
            $page->is_published = (bool) $validated['isPublished'];
        }
        $page->save();

        return response()->json(['success' => true]);
    }

    public function updateSection(Request $request, string $slug, string $sectionKey): JsonResponse
    {
        $page = CmsPage::where('slug', $slug)->firstOrFail();
        $section = CmsSection::where('cms_page_id', $page->id)
            ->where('section_key', $sectionKey)
            ->firstOrFail();

        $validated = $request->validate([
            'isEnabled' => 'sometimes|boolean',
            'sortOrder' => 'sometimes|integer|min:0',
            'content' => 'sometimes|array',
        ]);

        if (array_key_exists('isEnabled', $validated)) {
            $section->is_enabled = (bool) $validated['isEnabled'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $section->sort_order = (int) $validated['sortOrder'];
        }
        if (array_key_exists('content', $validated)) {
            $section->content = $validated['content'];
        }
        $section->save();

        return response()->json([
            'success' => true,
            'section' => [
                'id' => (string) $section->id,
                'key' => $section->section_key,
                'label' => $section->label,
                'isEnabled' => (bool) $section->is_enabled,
                'sortOrder' => (int) $section->sort_order,
                'content' => $section->content ?? [],
            ],
        ]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $maxKb = (int) config('cms.upload_max_kb', 10240);

        $request->validate(
            [
                'image' => 'required|image|max:'.$maxKb,
            ],
            [
                'image.max' => 'The image may not be greater than '.round($maxKb / 1024, 1).' MB. Compress it or use a smaller file.',
                'image.image' => 'The file must be an image (JPEG, PNG, GIF, or WebP).',
            ]
        );

        $path = $request->file('image')->store('cms_images', 'public');
        $url = asset('storage/'.$path);

        return response()->json([
            'success' => true,
            'url' => $url,
            'path' => $path,
        ]);
    }

    public function reorderSections(Request $request, string $slug): JsonResponse
    {
        $page = CmsPage::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.key' => 'required|string',
            'orders.*.sortOrder' => 'required|integer|min:0',
        ]);

        foreach ($validated['orders'] as $item) {
            CmsSection::where('cms_page_id', $page->id)
                ->where('section_key', $item['key'])
                ->update(['sort_order' => (int) $item['sortOrder']]);
        }

        return response()->json(['success' => true]);
    }
}
