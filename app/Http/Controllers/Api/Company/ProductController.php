<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $query = Product::where('company_id', $companyId);

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $products = $query->with(['variants' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])->orderBy('name')->get();
        $data = $products->map(fn (Product $p) => $this->productToArray($p));

        return response()->json($data->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        if ($request->hasFile('image')) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'category' => 'nullable|string|max:255',
                'stock' => 'required|integer|min:0',
                'image' => 'image|max:5120',
            ]);
            $path = $request->file('image')->store('products/'.$companyId, 'public');
            $validated['image'] = $path;
            $validated['company_id'] = $companyId;
            $product = Product::create($validated);
        } else {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'category' => 'nullable|string|max:255',
                'stock' => 'required|integer|min:0',
            ]);
            $validated['company_id'] = $companyId;
            $product = Product::create($validated);
        }

        $product->load(['variants' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);

        return response()->json([
            'success' => true,
            'product' => $this->productToArray($product),
            'message' => 'Product created successfully',
        ]);
    }

    public function storeVariant(Request $request, Product $product): JsonResponse
    {
        if ($product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:500',
            'price' => 'required|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
            'attributes' => 'nullable|array',
            'sortOrder' => 'sometimes|integer|min:0',
        ]);

        $variant = $product->variants()->create([
            'label' => $validated['label'],
            'price' => $validated['price'],
            'stock' => (int) ($validated['stock'] ?? 0),
            'status' => $validated['status'] ?? 'active',
            'attributes' => $validated['attributes'] ?? null,
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ]);

        return response()->json([
            'success' => true,
            'variant' => $this->variantToArray($variant),
            'message' => 'Variant created',
        ], 201);
    }

    public function updateVariant(Request $request, ProductVariant $productVariant): JsonResponse
    {
        $product = $productVariant->product;
        if (! $product || $product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'label' => 'sometimes|string|max:500',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
            'attributes' => 'nullable|array',
            'sortOrder' => 'sometimes|integer|min:0',
        ]);

        $updates = [];
        if (array_key_exists('label', $validated)) {
            $updates['label'] = $validated['label'];
        }
        if (array_key_exists('price', $validated)) {
            $updates['price'] = $validated['price'];
        }
        if (array_key_exists('stock', $validated)) {
            $updates['stock'] = $validated['stock'];
        }
        if (array_key_exists('status', $validated)) {
            $updates['status'] = $validated['status'];
        }
        if (array_key_exists('attributes', $validated)) {
            $updates['attributes'] = $validated['attributes'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $updates['sort_order'] = $validated['sortOrder'];
        }
        $productVariant->update($updates);

        return response()->json([
            'success' => true,
            'variant' => $this->variantToArray($productVariant->fresh()),
            'message' => 'Variant updated',
        ]);
    }

    public function destroyVariant(Request $request, ProductVariant $productVariant): JsonResponse
    {
        $product = $productVariant->product;
        if (! $product || $product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }
        $productVariant->delete();

        return response()->json(['success' => true, 'message' => 'Variant deleted']);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        if ($product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'category' => 'nullable|string|max:255',
            'stock' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        if ($product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    private function productToArray(Product $product): array
    {
        if (! $product->relationLoaded('variants')) {
            $product->load(['variants' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
        }

        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'description' => $product->description ?? '',
            'price' => (float) $product->price,
            'category' => $product->category ?? '',
            'image' => $product->image ? Storage::url($product->image) : null,
            'stock' => $product->stock,
            'status' => $product->status,
            'createdAt' => $product->created_at->format('Y-m-d'),
            'variants' => $product->variants->map(fn (ProductVariant $v) => $this->variantToArray($v))->values()->all(),
        ];
    }

    private function variantToArray(ProductVariant $v): array
    {
        return [
            'id' => (string) $v->id,
            'label' => $v->label,
            'price' => (float) $v->price,
            'stock' => (int) $v->stock,
            'status' => $v->status,
            'attributes' => $v->attributes ?? [],
            'sortOrder' => (int) $v->sort_order,
        ];
    }
}
