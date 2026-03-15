<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandingFaqController extends Controller
{
    public function index(): JsonResponse
    {
        $items = LandingFaq::orderBy('sort_order')->orderBy('id')->get();
        $data = $items->map(fn (LandingFaq $f) => [
            'id' => (string) $f->id,
            'question' => $f->question,
            'answer' => $f->answer,
            'sortOrder' => (int) $f->sort_order,
            'isActive' => (bool) $f->is_active,
            'createdAt' => $f->created_at?->format('c'),
            'updatedAt' => $f->updated_at?->format('c'),
        ]);

        return response()->json($data->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:5000',
            'sortOrder' => 'nullable|integer|min:0',
            'isActive' => 'sometimes|boolean',
        ]);

        $f = new LandingFaq();
        $f->question = $validated['question'];
        $f->answer = $validated['answer'];
        $f->sort_order = (int) ($validated['sortOrder'] ?? 0);
        $f->is_active = (bool) ($validated['isActive'] ?? true);
        $f->save();

        return response()->json([
            'success' => true,
            'faq' => [
                'id' => (string) $f->id,
                'question' => $f->question,
                'answer' => $f->answer,
                'sortOrder' => (int) $f->sort_order,
                'isActive' => (bool) $f->is_active,
                'createdAt' => $f->created_at?->format('c'),
                'updatedAt' => $f->updated_at?->format('c'),
            ],
        ], 201);
    }

    public function update(Request $request, LandingFaq $landing_faq): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'sometimes|string|max:500',
            'answer' => 'sometimes|string|max:5000',
            'sortOrder' => 'nullable|integer|min:0',
            'isActive' => 'sometimes|boolean',
        ]);

        if (array_key_exists('question', $validated)) {
            $landing_faq->question = $validated['question'];
        }
        if (array_key_exists('answer', $validated)) {
            $landing_faq->answer = $validated['answer'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $landing_faq->sort_order = (int) $validated['sortOrder'];
        }
        if (array_key_exists('isActive', $validated)) {
            $landing_faq->is_active = (bool) $validated['isActive'];
        }
        $landing_faq->save();

        return response()->json([
            'success' => true,
            'faq' => [
                'id' => (string) $landing_faq->id,
                'question' => $landing_faq->question,
                'answer' => $landing_faq->answer,
                'sortOrder' => (int) $landing_faq->sort_order,
                'isActive' => (bool) $landing_faq->is_active,
                'createdAt' => $landing_faq->created_at?->format('c'),
                'updatedAt' => $landing_faq->updated_at?->format('c'),
            ],
        ]);
    }

    public function destroy(LandingFaq $landing_faq): JsonResponse
    {
        $landing_faq->delete();
        return response()->json(['success' => true]);
    }
}
