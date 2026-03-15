<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (!$companyId) {
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
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->orderBy('name')->get();
        $data = $products->map(fn (Product $p) => $this->productToArray($p));

        return response()->json($data->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (!$companyId) {
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
            $path = $request->file('image')->store('products/' . $companyId, 'public');
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

        return response()->json([
            'success' => true,
            'product' => $this->productToArray($product),
            'message' => 'Product created successfully',
        ]);
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
        ];
    }
}
