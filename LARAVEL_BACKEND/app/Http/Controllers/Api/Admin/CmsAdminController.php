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

