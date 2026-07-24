<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogPostAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = BlogPost::orderByDesc('published_at')->orderByDesc('id')->get()
            ->map(fn (BlogPost $p) => $p->toAdminArray());

        return response()->json($posts->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request, true);

        $post = new BlogPost();
        $this->fill($post, $validated);
        $post->ensureSlug();
        if ($post->is_published && ! $post->published_at) {
            $post->published_at = now();
        }
        $post->save();

        return response()->json(['success' => true, 'post' => $post->toAdminArray()], 201);
    }

    public function update(Request $request, BlogPost $blogPost): JsonResponse
    {
        $validated = $this->validated($request, false);
        $this->fill($blogPost, $validated);
        if (empty($blogPost->slug)) {
            $blogPost->ensureSlug();
        }
        if ($blogPost->is_published && ! $blogPost->published_at) {
            $blogPost->published_at = now();
        }
        $blogPost->save();

        return response()->json(['success' => true, 'post' => $blogPost->fresh()->toAdminArray()]);
    }

    public function destroy(BlogPost $blogPost): JsonResponse
    {
        $blogPost->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'title' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'slug' => 'nullable|string|max:255|alpha_dash',
            'excerpt' => 'nullable|string|max:500',
            'body' => ($creating ? 'required' : 'sometimes').'|string|max:200000',
            'coverImage' => 'nullable|string|max:2048',
            'metaTitle' => 'nullable|string|max:255',
            'metaDescription' => 'nullable|string|max:2000',
            'ogImage' => 'nullable|string|max:2048',
            'publishedAt' => 'nullable|date',
            'isPublished' => 'sometimes|boolean',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fill(BlogPost $post, array $validated): void
    {
        $map = [
            'title' => 'title',
            'slug' => 'slug',
            'excerpt' => 'excerpt',
            'body' => 'body',
            'coverImage' => 'cover_image',
            'metaTitle' => 'meta_title',
            'metaDescription' => 'meta_description',
            'ogImage' => 'og_image',
        ];

        foreach ($map as $input => $column) {
            if (array_key_exists($input, $validated)) {
                $value = $validated[$input];
                if ($input === 'slug' && is_string($value) && $value !== '') {
                    $value = Str::slug($value);
                }
                $post->{$column} = $value;
            }
        }

        if (array_key_exists('publishedAt', $validated)) {
            $post->published_at = $validated['publishedAt'];
        }
        if (array_key_exists('isPublished', $validated)) {
            $post->is_published = (bool) $validated['isPublished'];
        }
    }
}
