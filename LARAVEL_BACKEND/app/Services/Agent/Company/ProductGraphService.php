<?php

namespace App\Services\Agent\Company;

use App\Models\Product;
use App\Models\ProductRelationship;
use App\Support\MoneyFormatter;

/**
 * Product knowledge graph — accessory, warranty, bundle, complement edges.
 */
final class ProductGraphService
{
    public const TYPES = ['accessory', 'warranty', 'bundle', 'complement', 'replacement'];

    /**
     * @return list<array<string, mixed>>
     */
    public function relationshipsForProduct(int $companyId, int $productId): array
    {
        $edges = ProductRelationship::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->with(['relatedProduct:id,name,price,stock,status'])
            ->limit(20)
            ->get();

        return $edges->map(function (ProductRelationship $edge) {
            $related = $edge->relatedProduct;

            return [
                'relationship_type' => $edge->relationship_type,
                'label' => $edge->label,
                'product' => $related ? [
                    'id' => $related->id,
                    'name' => $related->name,
                    'price' => (float) $related->price,
                    'stock' => $related->stock,
                    'status' => $related->status,
                ] : null,
            ];
        })->filter(fn ($r) => $r['product'] !== null)->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function graphForProduct(int $companyId, int $productId, ?string $currency = 'USD'): array
    {
        $product = Product::query()
            ->where('company_id', $companyId)
            ->where('id', $productId)
            ->first(['id', 'name', 'price', 'stock', 'category', 'description']);

        if (! $product) {
            return ['found' => false, 'message' => 'Product not found.'];
        }

        $edges = $this->relationshipsForProduct($companyId, $productId);

        return [
            'found' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => MoneyFormatter::format((float) $product->price, $currency),
                'stock' => $product->stock,
                'category' => $product->category,
            ],
            'relationships' => array_map(function ($edge) use ($currency) {
                if (isset($edge['product']['price'])) {
                    $edge['product']['price'] = MoneyFormatter::format((float) $edge['product']['price'], $currency);
                }

                return $edge;
            }, $edges),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchByName(int $companyId, string $query, int $limit = 5): array
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('name', 'like', '%'.$query.'%')
            ->limit($limit)
            ->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
            ->all();
    }
}
