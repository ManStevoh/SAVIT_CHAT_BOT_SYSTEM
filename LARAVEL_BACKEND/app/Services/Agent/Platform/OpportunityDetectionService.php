<?php

namespace App\Services\Agent\Platform;

use App\Models\BusinessOpportunity;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Services\Agent\Intelligence\IntelligenceOutcomeService;
use Illuminate\Support\Facades\DB;

/**
 * AI strategy analyst — detects bundles, stock gaps, catalog gaps (#21).
 */
final class OpportunityDetectionService
{
    public function __construct(
        protected IntelligenceOutcomeService $outcomes,
    ) {}

    /**
     * @return list<BusinessOpportunity>
     */
    public function detectForCompany(Company $company): array
    {
        $created = [];
        $companyId = (int) $company->id;

        foreach ($this->detectBundleOpportunities($companyId) as $opp) {
            $created[] = $this->storeIfNew($companyId, $opp);
        }
        foreach ($this->detectLowStockOpportunities($companyId) as $opp) {
            $created[] = $this->storeIfNew($companyId, $opp);
        }
        foreach ($this->detectSlowMovers($companyId) as $opp) {
            $created[] = $this->storeIfNew($companyId, $opp);
        }

        return array_values(array_filter($created));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectBundleOpportunities(int $companyId): array
    {
        $pairs = DB::table('order_products as a')
            ->join('order_products as b', function ($join) {
                $join->on('a.order_id', '=', 'b.order_id')->whereColumn('a.id', '<', 'b.id');
            })
            ->join('orders as o', 'o.id', '=', 'a.order_id')
            ->where('o.company_id', $companyId)
            ->where('o.payment_status', 'paid')
            ->where('o.created_at', '>=', now()->subDays(90))
            ->select('a.name as product_a', 'b.name as product_b', DB::raw('COUNT(*) as pair_count'))
            ->groupBy('a.name', 'b.name')
            ->having('pair_count', '>=', 3)
            ->orderByDesc('pair_count')
            ->limit(5)
            ->get();

        $out = [];
        foreach ($pairs as $pair) {
            $out[] = [
                'opportunity_type' => 'bundle',
                'title' => "Bundle: {$pair->product_a} + {$pair->product_b}",
                'description' => 'Customers frequently buy these products together. Consider a bundle offer.',
                'evidence' => [
                    'product_a' => $pair->product_a,
                    'product_b' => $pair->product_b,
                    'co_purchase_count' => (int) $pair->pair_count,
                    'period_days' => 90,
                ],
                'estimated_impact' => [
                    'type' => 'revenue',
                    'note' => 'Bundling may increase average order value',
                ],
                'priority' => 'medium',
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectLowStockOpportunities(int $companyId): array
    {
        $threshold = (int) config('agent.company.low_stock_threshold', 5);
        $products = Product::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('stock', '<=', $threshold)
            ->limit(10)
            ->get(['name', 'stock']);

        $out = [];
        foreach ($products as $p) {
            $out[] = [
                'opportunity_type' => 'restock',
                'title' => "Restock: {$p->name}",
                'description' => "Stock is low ({$p->stock} units). Restock or promote alternatives.",
                'evidence' => ['product' => $p->name, 'stock' => $p->stock, 'threshold' => $threshold],
                'estimated_impact' => ['type' => 'risk_reduction', 'note' => 'Avoid lost sales from stockouts'],
                'priority' => $p->stock <= 1 ? 'high' : 'medium',
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectSlowMovers(int $companyId): array
    {
        $soldIds = OrderProduct::query()
            ->whereHas('order', fn ($q) => $q
                ->where('company_id', $companyId)
                ->where('payment_status', 'paid')
                ->where('created_at', '>=', now()->subDays(60)))
            ->distinct()
            ->pluck('name');

        $slow = Product::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('stock', '>', 0)
            ->whereNotIn('name', $soldIds)
            ->limit(5)
            ->get(['name', 'stock']);

        $out = [];
        foreach ($slow as $p) {
            $out[] = [
                'opportunity_type' => 'clear_inventory',
                'title' => "Promote slow mover: {$p->name}",
                'description' => 'No sales in 60 days despite stock on hand. Consider discount or campaign.',
                'evidence' => ['product' => $p->name, 'stock' => $p->stock, 'days_without_sale' => 60],
                'estimated_impact' => ['type' => 'inventory', 'note' => 'Free capital tied in slow stock'],
                'priority' => 'low',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $opp
     */
    private function storeIfNew(int $companyId, array $opp): ?BusinessOpportunity
    {
        $exists = BusinessOpportunity::query()
            ->where('company_id', $companyId)
            ->where('opportunity_type', $opp['opportunity_type'])
            ->where('title', $opp['title'])
            ->where('status', 'open')
            ->exists();

        if ($exists) {
            return null;
        }

        $created = BusinessOpportunity::create([
            'company_id' => $companyId,
            'opportunity_type' => $opp['opportunity_type'],
            'title' => $opp['title'],
            'description' => $opp['description'],
            'evidence' => $opp['evidence'] ?? null,
            'estimated_impact' => $opp['estimated_impact'] ?? null,
            'priority' => $opp['priority'] ?? 'medium',
            'status' => 'open',
            'detected_at' => now(),
        ]);

        $company = Company::find($companyId);
        if ($company) {
            $this->outcomes->seedFromOpportunity(
                $company,
                (int) $created->id,
                (string) $created->title,
                (string) $created->description,
            );
        }

        return $created;
    }
}
