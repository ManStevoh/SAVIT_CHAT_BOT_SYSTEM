<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\AI\FaqEmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (!$companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $query = Faq::where('company_id', $companyId);

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('question', 'like', '%' . $request->search . '%')
                    ->orWhere('answer', 'like', '%' . $request->search . '%');
            });
        }

        $faqs = $query->orderBy('created_at')->get();
        $data = $faqs->map(fn (Faq $faq) => $this->faqToArray($faq));

        return response()->json($data->values()->all());
    }

    public function store(Request $request, FaqEmbeddingService $embeddings): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (!$companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'question' => 'required|string',
            'answer' => 'required|string',
            'category' => 'nullable|string|max:255',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string',
        ]);

        $validated['company_id'] = $companyId;
        $faq = Faq::create($validated);
        try {
            $embeddings->syncFaq($faq->fresh());
        } catch (\Throwable) {
            // FAQ save succeeds even if embedding sync fails
        }

        return response()->json([
            'success' => true,
            'faq' => $this->faqToArray($faq),
            'message' => 'FAQ created successfully',
        ]);
    }

    public function update(Request $request, Faq $faq, FaqEmbeddingService $embeddings): JsonResponse
    {
        if ($faq->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        // Frontend sends camelCase isActive; normalize for validation and model
        if ($request->has('isActive')) {
            $request->merge(['is_active' => $request->boolean('isActive')]);
        }

        $validated = $request->validate([
            'question' => 'sometimes|string',
            'answer' => 'sometimes|string',
            'category' => 'nullable|string|max:255',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string',
            'is_active' => 'sometimes|boolean',
        ]);

        $faq->update($validated);
        if (array_key_exists('question', $validated)) {
            try {
                $embeddings->syncFaq($faq->fresh());
            } catch (\Throwable) {
                // ignore embedding failures
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'FAQ updated successfully',
        ]);
    }

    public function destroy(Request $request, Faq $faq): JsonResponse
    {
        if ($faq->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $faq->delete();

        return response()->json([
            'success' => true,
            'message' => 'FAQ deleted successfully',
        ]);
    }

    private function faqToArray(Faq $faq): array
    {
        return [
            'id' => (string) $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'category' => $faq->category ?? '',
            'keywords' => $faq->keywords ?? [],
            'isActive' => $faq->is_active,
            'usageCount' => $faq->usage_count,
            'createdAt' => $faq->created_at->format('Y-m-d'),
        ];
    }
}
