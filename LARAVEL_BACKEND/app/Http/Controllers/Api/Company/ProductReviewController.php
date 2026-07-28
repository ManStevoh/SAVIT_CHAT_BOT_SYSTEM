<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feature 16 (reviews & ratings) moderation surface: list + approve/reject reviews left by
 * storefront shoppers on the merchant's products.
 */
class ProductReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $query = ProductReview::where('company_id', $companyId)->with('product')->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_approved', $request->status === 'approved');
        }

        $reviews = $query->get()->map(fn (ProductReview $review) => $this->toArray($review));

        return response()->json($reviews->values()->all());
    }

    public function update(Request $request, ProductReview $productReview): JsonResponse
    {
        if ($productReview->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'isApproved' => 'required|boolean',
        ]);

        $productReview->update(['is_approved' => $validated['isApproved']]);

        return response()->json([
            'success' => true,
            'review' => $this->toArray($productReview->fresh('product')),
        ]);
    }

    public function destroy(Request $request, ProductReview $productReview): JsonResponse
    {
        if ($productReview->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $productReview->delete();

        return response()->json(['success' => true]);
    }

    /** @return array<string, mixed> */
    private function toArray(ProductReview $review): array
    {
        return [
            'id' => (string) $review->id,
            'productId' => (string) $review->product_id,
            'productName' => $review->product?->name ?? 'Product',
            'authorName' => $review->author_name,
            'rating' => (int) $review->rating,
            'body' => $review->body,
            'isApproved' => (bool) $review->is_approved,
            'createdAt' => $review->created_at?->toIso8601String(),
        ];
    }
}
