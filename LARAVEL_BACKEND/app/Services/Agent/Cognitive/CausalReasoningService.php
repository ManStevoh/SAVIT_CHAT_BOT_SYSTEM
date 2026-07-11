<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;

/**
 * Causal reasoning (#50) — hypothesize likely causes before recommending action.
 */
final class CausalReasoningService
{
    /**
     * @return array{metric: string, change: string, likely_causes: list<array{cause: string, likelihood: string}>}
     */
    public function analyzeSalesChange(Company $company): array
    {
        $companyId = (int) $company->id;
        $recent = (float) Order::query()
            ->where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(14))
            ->sum('total');

        $prior = (float) Order::query()
            ->where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [now()->subDays(28), now()->subDays(14)])
            ->sum('total');

        $change = match (true) {
            $prior <= 0 => 'unknown',
            $recent < $prior * 0.85 => 'dropped',
            $recent > $prior * 1.15 => 'increased',
            default => 'stable',
        };

        $causes = [];
        if ($change === 'dropped') {
            $lowStock = Product::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->where('stock', '<=', 2)
                ->count();
            if ($lowStock > 0) {
                $causes[] = ['cause' => 'stock_shortage', 'likelihood' => 'high'];
            }
            $causes[] = ['cause' => 'marketing_gap', 'likelihood' => 'medium'];
            $causes[] = ['cause' => 'seasonality', 'likelihood' => 'medium'];
            $causes[] = ['cause' => 'price_increase', 'likelihood' => 'low'];
        }

        return [
            'metric' => 'revenue_14d',
            'change' => $change,
            'likely_causes' => $causes,
        ];
    }
}
