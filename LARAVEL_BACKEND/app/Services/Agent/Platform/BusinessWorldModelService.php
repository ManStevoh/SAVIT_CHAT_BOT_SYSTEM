<?php

namespace App\Services\Agent\Platform;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CustomerIntentChain;
use App\Models\CustomerMemory;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\BusinessWorldSnapshot;

/**
 * Living internal model of the business (world model #19).
 */
final class BusinessWorldModelService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Company $company): array
    {
        $companyId = (int) $company->id;
        $lowStockThreshold = (int) config('agent.company.low_stock_threshold', 5);

        $paidOrders30d = Order::query()
            ->where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $revenue30d = (float) Order::query()
            ->where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('total');

        $pendingPayments = Order::query()
            ->where('company_id', $companyId)
            ->where('payment_status', 'pending')
            ->count();

        $activeProducts = Product::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->count();

        $lowStock = Product::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('stock', '<=', $lowStockThreshold)
            ->orderBy('stock')
            ->limit(15)
            ->get(['id', 'name', 'stock', 'price']);

        $customerPhones = Order::query()
            ->where('company_id', $companyId)
            ->whereNotNull('customer_phone')
            ->distinct()
            ->count('customer_phone');

        $goals = $company->settings?->agent_business_goals ?? array_keys(config('agent.business_goals', []));

        return [
            'updated_at' => now()->toIso8601String(),
            'customers' => [
                'unique_phones' => $customerPhones,
                'intent_chains_active' => CustomerIntentChain::where('company_id', $companyId)
                    ->where('last_active_at', '>=', now()->subDays(14))->count(),
                'memories_stored' => CustomerMemory::where('company_id', $companyId)->count(),
            ],
            'products' => [
                'active_count' => $activeProducts,
                'low_stock' => $lowStock->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'stock' => $p->stock,
                ])->all(),
            ],
            'orders' => [
                'paid_last_30_days' => $paidOrders30d,
                'revenue_last_30_days' => $revenue30d,
                'pending_payment' => $pendingPayments,
            ],
            'goals' => $goals,
            'risks' => array_values(array_filter([
                $pendingPayments > 5 ? 'High pending payment count' : null,
                $lowStock->count() > 0 ? 'Low stock on '.$lowStock->count().' products' : null,
            ])),
        ];
    }

    public function snapshot(Company $company, string $trigger = 'scheduled'): BusinessWorldSnapshot
    {
        return BusinessWorldSnapshot::create([
            'company_id' => $company->id,
            'world_model' => $this->build($company),
            'trigger' => $trigger,
            'created_at' => now(),
        ]);
    }

    public function getLatest(int $companyId): ?array
    {
        $row = BusinessWorldSnapshot::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->first();

        return $row?->world_model;
    }

    public function getForPrompt(Company $company): string
    {
        $world = $this->getLatest((int) $company->id) ?? $this->build($company);
        $orders = $world['orders'] ?? [];
        $products = $world['products'] ?? [];
        $risks = $world['risks'] ?? [];

        $lines = ['Business world model (live snapshot):'];
        $lines[] = '- Revenue (30d): '.($orders['revenue_last_30_days'] ?? 0);
        $lines[] = '- Paid orders (30d): '.($orders['paid_last_30_days'] ?? 0);
        $lines[] = '- Pending payments: '.($orders['pending_payment'] ?? 0);
        $lines[] = '- Active products: '.($products['active_count'] ?? 0);
        if ($risks !== []) {
            $lines[] = '- Risks: '.implode('; ', $risks);
        }

        return implode("\n", $lines);
    }
}
