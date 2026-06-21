<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Testimonial::orderBy('sort_order')->orderBy('id')->get();
        $data = $items->map(fn (Testimonial $t) => [
            'id' => (string) $t->id,
            'name' => $t->name,
            'role' => $t->role ?? '',
            'content' => $t->content,
            'rating' => (int) $t->rating,
            'sortOrder' => (int) $t->sort_order,
            'isActive' => (bool) $t->is_active,
            'createdAt' => $t->created_at?->format('c'),
            'updatedAt' => $t->updated_at?->format('c'),
        ]);

        return response()->json($data->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'nullable|string|max:255',
            'content' => 'required|string|max:5000',
            'rating' => 'nullable|integer|min:1|max:5',
            'sortOrder' => 'nullable|integer|min:0',
            'isActive' => 'sometimes|boolean',
        ]);

        $t = new Testimonial();
        $t->name = $validated['name'];
        $t->role = $validated['role'] ?? null;
        $t->content = $validated['content'];
        $t->rating = (int) ($validated['rating'] ?? 5);
        $t->sort_order = (int) ($validated['sortOrder'] ?? 0);
        $t->is_active = (bool) ($validated['isActive'] ?? true);
        $t->save();

        return response()->json([
            'success' => true,
            'testimonial' => [
                'id' => (string) $t->id,
                'name' => $t->name,
                'role' => $t->role ?? '',
                'content' => $t->content,
                'rating' => (int) $t->rating,
                'sortOrder' => (int) $t->sort_order,
                'isActive' => (bool) $t->is_active,
                'createdAt' => $t->created_at?->format('c'),
                'updatedAt' => $t->updated_at?->format('c'),
            ],
        ], 201);
    }

    public function update(Request $request, Testimonial $testimonial): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'role' => 'nullable|string|max:255',
            'content' => 'sometimes|string|max:5000',
            'rating' => 'nullable|integer|min:1|max:5',
            'sortOrder' => 'nullable|integer|min:0',
            'isActive' => 'sometimes|boolean',
        ]);

        if (array_key_exists('name', $validated)) {
            $testimonial->name = $validated['name'];
        }
        if (array_key_exists('role', $validated)) {
            $testimonial->role = $validated['role'] ?: null;
        }
        if (array_key_exists('content', $validated)) {
            $testimonial->content = $validated['content'];
        }
        if (array_key_exists('rating', $validated)) {
            $testimonial->rating = (int) $validated['rating'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $testimonial->sort_order = (int) $validated['sortOrder'];
        }
        if (array_key_exists('isActive', $validated)) {
            $testimonial->is_active = (bool) $validated['isActive'];
        }
        $testimonial->save();

        return response()->json([
            'success' => true,
            'testimonial' => [
                'id' => (string) $testimonial->id,
                'name' => $testimonial->name,
                'role' => $testimonial->role ?? '',
                'content' => $testimonial->content,
                'rating' => (int) $testimonial->rating,
                'sortOrder' => (int) $testimonial->sort_order,
                'isActive' => (bool) $testimonial->is_active,
                'createdAt' => $testimonial->created_at?->format('c'),
                'updatedAt' => $testimonial->updated_at?->format('c'),
            ],
        ]);
    }

    public function destroy(Testimonial $testimonial): JsonResponse
    {
        $testimonial->delete();
        return response()->json(['success' => true]);
    }
}
