<?php

namespace App\Services\Agent\Graph;

use App\Models\BusinessGraphEdge;
use App\Models\BusinessGraphNode;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductRelationship;
use App\Models\WhatsAppCampaign;
use Illuminate\Support\Facades\DB;

/**
 * Business Graph v2 — traversable nodes beyond products (suppliers, warehouses, campaigns).
 */
final class BusinessGraphV2Service
{
    /**
     * Sync graph nodes/edges from live company data.
     */
    public function syncFromCompany(Company $company): array
    {
        $stats = ['nodes' => 0, 'edges' => 0];

        Product::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->limit(500)
            ->get(['id', 'name', 'category', 'stock'])
            ->each(function (Product $product) use ($company, &$stats) {
                $node = $this->upsertNode(
                    $company,
                    BusinessGraphNode::TYPE_PRODUCT,
                    'product',
                    (int) $product->id,
                    $product->name,
                    ['category' => $product->category, 'stock' => $product->stock],
                );
                $stats['nodes']++;

                if ($product->category) {
                    $catNode = $this->upsertNode(
                        $company,
                        BusinessGraphNode::TYPE_CATEGORY,
                        'category',
                        crc32($product->category) & 0x7FFFFFFF,
                        $product->category,
