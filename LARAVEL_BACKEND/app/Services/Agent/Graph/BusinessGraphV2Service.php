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
                    );
                    $stats['nodes']++;
                    if ($this->link($company, $node->id, $catNode->id, 'in_category')) {
                        $stats['edges']++;
                    }
                }
            });

        ProductRelationship::query()
            ->where('company_id', $company->id)
            ->limit(500)
            ->get()
            ->each(function (ProductRelationship $rel) use ($company, &$stats) {
                $from = $this->upsertNode(
                    $company,
                    BusinessGraphNode::TYPE_PRODUCT,
                    'product',
                    (int) $rel->product_id,
                    'Product #'.$rel->product_id,
                );
                $to = $this->upsertNode(
                    $company,
                    BusinessGraphNode::TYPE_PRODUCT,
                    'product',
                    (int) $rel->related_product_id,
                    'Product #'.$rel->related_product_id,
                );
                if ($this->link($company, $from->id, $to->id, $rel->relationship_type)) {
                    $stats['edges']++;
                }
            });

        WhatsAppCampaign::query()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'name', 'status', 'sent_count'])
            ->each(function (WhatsAppCampaign $campaign) use ($company, &$stats) {
                $this->upsertNode(
                    $company,
                    BusinessGraphNode::TYPE_CAMPAIGN,
                    'whatsapp_campaign',
                    (int) $campaign->id,
                    $campaign->name,
                    ['status' => $campaign->status, 'sent_count' => $campaign->sent_count],
                );
                $stats['nodes']++;
            });

        Order::query()
            ->where('company_id', $company->id)
            ->where('payment_status', 'paid')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'order_number', 'customer_phone', 'total'])
            ->each(function (Order $order) use ($company, &$stats) {
                $orderNode = $this->upsertNode(
                    $company,
                    BusinessGraphNode::TYPE_ORDER,
                    'order',
                    (int) $order->id,
                    $order->order_number,
                    ['total' => (float) $order->total],
                );
                $stats['nodes']++;

                if ($order->customer_phone) {
                    $phone = preg_replace('/\D+/', '', $order->customer_phone) ?: $order->customer_phone;
                    $customerNode = $this->upsertNode(
                        $company,
                        BusinessGraphNode::TYPE_CUSTOMER,
                        'customer_phone',
                        crc32($phone) & 0x7FFFFFFF,
                        $phone,
                    );
                    $stats['nodes']++;
                    if ($this->link($company, $customerNode->id, $orderNode->id, 'placed_order')) {
                        $stats['edges']++;
                    }
                }
            });

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function addManualNode(
        Company $company,
        string $nodeType,
        string $label,
        array $metadata = [],
    ): BusinessGraphNode {
        return $this->upsertNode(
            $company,
            $nodeType,
            'manual',
            crc32($label) & 0x7FFFFFFF,
            $label,
            $metadata,
        );
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, stats: array<string, int>}
     */
    public function exportGraph(Company $company, int $nodeLimit = 200): array
    {
        $nodes = BusinessGraphNode::query()
            ->where('company_id', $company->id)
            ->orderByDesc('updated_at')
            ->limit($nodeLimit)
            ->get();

        $nodeIds = $nodes->pluck('id')->all();

        $edges = BusinessGraphEdge::query()
            ->where('company_id', $company->id)
            ->whereIn('from_node_id', $nodeIds)
            ->whereIn('to_node_id', $nodeIds)
            ->limit(500)
            ->get();

        return [
            'stats' => [
                'nodes' => BusinessGraphNode::where('company_id', $company->id)->count(),
                'edges' => BusinessGraphEdge::where('company_id', $company->id)->count(),
            ],
            'nodes' => $nodes->map(fn (BusinessGraphNode $n) => [
                'id' => $n->id,
                'type' => $n->node_type,
                'label' => $n->label,
                'refType' => $n->ref_type,
                'refId' => $n->ref_id,
                'metadata' => $n->metadata,
            ])->all(),
            'edges' => $edges->map(fn (BusinessGraphEdge $e) => [
                'from' => $e->from_node_id,
                'to' => $e->to_node_id,
                'type' => $e->edge_type,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function upsertNode(
        Company $company,
        string $nodeType,
        string $refType,
        int $refId,
        string $label,
        array $metadata = [],
    ): BusinessGraphNode {
        return BusinessGraphNode::updateOrCreate(
            [
                'company_id' => $company->id,
                'node_type' => $nodeType,
                'ref_type' => $refType,
                'ref_id' => $refId,
            ],
            [
                'label' => mb_substr($label, 0, 255),
                'metadata' => $metadata,
            ],
        );
    }

    private function link(Company $company, int $fromId, int $toId, string $edgeType): bool
    {
        if ($fromId === $toId) {
            return false;
        }

        BusinessGraphEdge::firstOrCreate(
            [
                'from_node_id' => $fromId,
                'to_node_id' => $toId,
                'edge_type' => $edgeType,
            ],
            ['company_id' => $company->id],
        );

        return true;
    }
}
