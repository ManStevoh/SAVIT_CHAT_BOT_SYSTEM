<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
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

        $products = $query
            ->with($this->productRelations())
            ->orderBy('name')
            ->get();
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
            $this->createImageRecord($product, null, $path, true, 0, null);
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

        $product->load($this->productRelations());
        $this->syncProductEmbeddings($product);

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
            'image' => 'nullable|image|max:5120',
            'isPrimaryImage' => 'sometimes|boolean',
        ]);

        $variant = $product->variants()->create([
            'label' => $validated['label'],
            'price' => $validated['price'],
            'stock' => (int) ($validated['stock'] ?? 0),
            'status' => $validated['status'] ?? 'active',
            'attributes' => $validated['attributes'] ?? null,
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products/'.$product->company_id.'/variants', 'public');
            $this->createImageRecord(
                $product,
                $variant,
                $path,
                (bool) ($validated['isPrimaryImage'] ?? true),
                0,
                $validated['label']
            );
        }

        return response()->json([
            'success' => true,
            'variant' => $this->variantToArray($variant->fresh()->load('images')),
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
            'image' => 'nullable|image|max:5120',
            'isPrimaryImage' => 'sometimes|boolean',
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

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products/'.$product->company_id.'/variants', 'public');
            $this->createImageRecord(
                $product,
                $productVariant,
                $path,
                (bool) ($validated['isPrimaryImage'] ?? true),
                0,
                $productVariant->label
            );
        }

        return response()->json([
            'success' => true,
            'variant' => $this->variantToArray($productVariant->fresh()->load('images')),
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
            'image' => 'nullable|image|max:5120',
        ]);

        unset($validated['image']);
        $product->update($validated);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products/'.$product->company_id, 'public');
            $product->update(['image' => $path]);
            $this->createImageRecord($product, null, $path, true, 0, $product->name);
        }

        $product->load($this->productRelations());
        $this->syncProductEmbeddings($product);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'product' => $this->productToArray($product),
        ]);
    }

    public function storeProductImage(Request $request, Product $product): JsonResponse
    {
        if ($product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'image' => 'required|image|max:5120',
            'isPrimary' => 'sometimes|boolean',
            'sortOrder' => 'sometimes|integer|min:0',
            'altText' => 'nullable|string|max:255',
        ]);

        $path = $request->file('image')->store('products/'.$product->company_id, 'public');
        $image = $this->createImageRecord(
            $product,
            null,
            $path,
            (bool) ($validated['isPrimary'] ?? false),
            (int) ($validated['sortOrder'] ?? 0),
            $validated['altText'] ?? $product->name
        );

        if ($product->image === null || $image->is_primary) {
            $product->update(['image' => $path]);
        }

        return response()->json([
            'success' => true,
            'image' => $this->productImageToArray($image->fresh()),
            'message' => 'Product image uploaded',
        ], 201);
    }

    public function storeVariantImage(Request $request, ProductVariant $productVariant): JsonResponse
    {
        $product = $productVariant->product;
        if (! $product || $product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'image' => 'required|image|max:5120',
            'isPrimary' => 'sometimes|boolean',
            'sortOrder' => 'sometimes|integer|min:0',
            'altText' => 'nullable|string|max:255',
        ]);

        $path = $request->file('image')->store('products/'.$product->company_id.'/variants', 'public');
        $image = $this->createImageRecord(
            $product,
            $productVariant,
            $path,
            (bool) ($validated['isPrimary'] ?? false),
            (int) ($validated['sortOrder'] ?? 0),
            $validated['altText'] ?? $productVariant->label
        );

        return response()->json([
            'success' => true,
            'image' => $this->productImageToArray($image->fresh()),
            'message' => 'Variant image uploaded',
        ], 201);
    }

    public function updateImage(Request $request, ProductImage $productImage): JsonResponse
    {
        $product = $productImage->product;
        if (! $product || $product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'isPrimary' => 'sometimes|boolean',
            'sortOrder' => 'sometimes|integer|min:0',
            'altText' => 'nullable|string|max:255',
        ]);

        $updates = [];
        if (array_key_exists('isPrimary', $validated)) {
            $updates['is_primary'] = (bool) $validated['isPrimary'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $updates['sort_order'] = (int) $validated['sortOrder'];
        }
        if (array_key_exists('altText', $validated)) {
            $updates['alt_text'] = $validated['altText'];
        }

        if ($updates !== []) {
            $productImage->update($updates);
        }

        if (($updates['is_primary'] ?? false) === true) {
            $this->setPrimaryImage($productImage);
        }

        if ($productImage->product_variant_id === null && $productImage->is_primary) {
            $product->update(['image' => $productImage->path]);
        }

        return response()->json([
            'success' => true,
            'image' => $this->productImageToArray($productImage->fresh()),
            'message' => 'Image updated',
        ]);
    }

    public function destroyImage(Request $request, ProductImage $productImage): JsonResponse
    {
        $product = $productImage->product;
        if (! $product || $product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $deletedPath = $productImage->path;
        $wasPrimaryProductImage = $productImage->product_variant_id === null && $productImage->is_primary;
        $productImage->delete();

        if ($deletedPath && Storage::disk('public')->exists($deletedPath)) {
            Storage::disk('public')->delete($deletedPath);
        }

        if ($wasPrimaryProductImage) {
            $fallback = ProductImage::where('product_id', $product->id)
                ->whereNull('product_variant_id')
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($fallback) {
                $this->setPrimaryImage($fallback);
                $product->update(['image' => $fallback->path]);
            } else {
                $product->update(['image' => null]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Image deleted']);
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
        if (! $product->relationLoaded('variants') || ! $product->relationLoaded('images')) {
            $product->load($this->productRelations());
        }

        $primary = $product->images->firstWhere('is_primary', true);
        $fallback = $primary ?? $product->images->first();
        $imageUrl = $fallback ? Storage::url($fallback->path) : ($product->image ? Storage::url($product->image) : null);

        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'description' => $product->description ?? '',
            'price' => (float) $product->price,
            'category' => $product->category ?? '',
            'image' => $imageUrl,
            'stock' => $product->stock,
            'status' => $product->status,
            'createdAt' => $product->created_at->format('Y-m-d'),
            'images' => $product->images->map(fn (ProductImage $img) => $this->productImageToArray($img))->values()->all(),
            'variants' => $product->variants->map(fn (ProductVariant $v) => $this->variantToArray($v))->values()->all(),
        ];
    }

    private function variantToArray(ProductVariant $v): array
    {
        if (! $v->relationLoaded('images')) {
            $v->load('images');
        }

        $primary = $v->images->firstWhere('is_primary', true) ?? $v->images->first();

        return [
            'id' => (string) $v->id,
            'label' => $v->label,
            'price' => (float) $v->price,
            'stock' => (int) $v->stock,
            'status' => $v->status,
            'attributes' => $v->attributes ?? [],
            'sortOrder' => (int) $v->sort_order,
            'image' => $primary ? Storage::url($primary->path) : null,
            'images' => $v->images->map(fn (ProductImage $img) => $this->productImageToArray($img))->values()->all(),
        ];
    }

    private function productImageToArray(ProductImage $image): array
    {
        return [
            'id' => (string) $image->id,
            'productId' => (string) $image->product_id,
            'productVariantId' => $image->product_variant_id ? (string) $image->product_variant_id : null,
            'url' => Storage::url($image->path),
            'path' => $image->path,
            'altText' => $image->alt_text,
            'isPrimary' => (bool) $image->is_primary,
            'sortOrder' => (int) $image->sort_order,
        ];
    }

    private function createImageRecord(
        Product $product,
        ?ProductVariant $variant,
        string $path,
        bool $isPrimary,
        int $sortOrder,
        ?string $altText
    ): ProductImage {
        $image = ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'path' => $path,
            'alt_text' => $altText,
            'is_primary' => $isPrimary,
            'sort_order' => $sortOrder,
        ]);

        if ($isPrimary) {
            $this->setPrimaryImage($image);
        }

        return $image;
    }

    private function setPrimaryImage(ProductImage $image): void
    {
        ProductImage::where('product_id', $image->product_id)
            ->where(function ($q) use ($image) {
                if ($image->product_variant_id === null) {
                    $q->whereNull('product_variant_id');
                } else {
                    $q->where('product_variant_id', $image->product_variant_id);
                }
            })
            ->where('id', '!=', $image->id)
            ->update(['is_primary' => false]);

        if (! $image->is_primary) {
            $image->update(['is_primary' => true]);
        }
    }

    private function productRelations(): array
    {
        return [
            'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
            'variants' => fn ($q) => $q
                ->orderBy('sort_order')
                ->orderBy('id')
                ->with(['images' => fn ($iq) => $iq->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')]),
        ];
    }

    private function syncProductEmbeddings(Product $product): void
    {
        if ($product->status !== 'active') {
            return;
        }

        try {
            app(\App\Services\AI\KnowledgeChunkService::class)->syncProduct($product);
        } catch (\Throwable) {
            // Non-blocking — weekly sync command will backfill
        }
    }
}
