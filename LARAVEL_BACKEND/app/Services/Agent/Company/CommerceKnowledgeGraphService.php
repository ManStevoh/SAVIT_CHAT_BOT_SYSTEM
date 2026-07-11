<?php

namespace App\Services\Agent\Company;

use App\Models\Order;
use App\Models\Product;
use App\Services\Agent\Company\ProductGraphService;
use App\Support\MoneyFormatter;

/**
 * Relationship traversal: Customer → Orders → Products → graph edges → related catalog.
 */
final class CommerceKnowledgeGraphService
{
    public function __construct(protected ProductGraphService $productGraph) {}
    /**
     * @return array<string, mixed>
     */
    public function traceCustomer(int $companyId, string $customerPhone, ?string $query = null, ?string $currency = 'USD'): array
    {
        $phone = preg_replace('/\D+/', '', $customerPhone) ?? $customerPhone;

        $orders = Order::query()
            ->where('company_id', $companyId)
            ->where('customer_phone', $phone)
            ->with(['orderProducts:id,order_id,name,quantity,price'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'order_number', 'status', 'payment_status', 'total', 'created_at']);

        $pastProducts = [];
        foreach ($orders as $order) {
            foreach ($order->orderProducts as $item) {
                $pastProducts[] = $item->name;
            }
        }
        $pastProducts = array_values(array_unique($pastProducts));

        $related = [];
        if ($pastProducts !== [] || $query) {
            $searchTerms = $query ? [$query] : array_slice($pastProducts, 0, 2);
            foreach ($searchTerms as $term) {
                $matches = Product::query()
                    ->where('company_id', $companyId)
                    ->where('status', 'active')
                    ->where(function ($q) use ($term) {
                        $q->where('name', 'like', '%'.$term.'%')
                            ->orWhere('description', 'like', '%'.$term.'%')
                            ->orWhere('category', 'like', '%'.$term.'%');
                    })
                    ->limit(5)
                    ->get(['id', 'name', 'price', 'stock', 'category']);
                foreach ($matches as $p) {
                    $graphEdges = $this->productGraph->relationshipsForProduct($companyId, (int) $p->id);
                    $related[$p->id] = [
                        'id' => $p->id,
                        'name' => $p->name,
                        'price' => MoneyFormatter::format((float) $p->price, $currency),
                        'stock' => $p->stock,
                        'category' => $p->category,
                        'linked_via' => $term,
                        'graph_edges' => $graphEdges,
                    ];
                }
            }
        }

        return [
            'customer_phone' => $phone,
            'graph' => [
                'customer' => ['phone' => $phone],
                'orders' => $orders->map(fn (Order $o) => [
                    'order_number' => $o->order_number,
                    'status' => $o->status,
                    'payment_status' => $o->payment_status,
                    'total' => MoneyFormatter::format((float) $o->total, $currency),
                    'date' => $o->created_at?->toDateString(),
                    'products' => $o->orderProducts->map(fn ($i) => "{$i->quantity}x {$i->name}")->all(),
                ])->all(),
                'past_product_names' => $pastProducts,
                'related_catalog' => array_values($related),
            ],
        ];
    }
}
