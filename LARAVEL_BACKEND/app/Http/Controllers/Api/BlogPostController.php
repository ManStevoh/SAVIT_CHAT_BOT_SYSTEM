<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;

class BlogPostController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = BlogPost::published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (BlogPost $post) {
                $data = $post->toPublicArray();
                unset($data['body']);

                return $data;
            });

        return response()->json(['posts' => $posts->values()->all()]);
    }

    public function show(string $slug): JsonResponse
    {
        $post = BlogPost::published()->where('slug', $slug)->first();
        if (! $post) {
            return response()->json(['message' => 'Post not found'], 404);
        }

        return response()->json(['post' => $post->toPublicArray()]);
    }
}
