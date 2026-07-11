<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Company;
use App\Models\Order;

/**
 * Future prediction (#49) — probabilistic demand planning from trends.
 */
final class ForecastService
{
    /**
     * @return array{forecast_orders_7d: int, trend: string, confidence: string, factors: list<string>}
     */
    public function demandForecast(Company $company): array
    {
        $companyId = (int) $company->id;
        $last7 = Order::query()
            ->where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $prev7 = Order::query()
            ->where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->count();

        $trend = match (true) {
            $last7 > $prev7 * 1.15 => 'growing',
            $last7 < $prev7 * 0.85 => 'declining',
            default => 'stable',
        };

        $forecast = (int) round($last7 * match ($trend) {
            'growing' => 1.1,
            'declining' => 0.9,
            default => 1.0,
        });

        $factors = ['paid_orders_last_7d' => $last7, 'paid_orders_prev_7d' => $prev7];

        return [
            'forecast_orders_7d' => max(0, $forecast),
            'trend' => $trend,
            'confidence' => $prev7 > 0 ? 'medium' : 'low',
            'factors' => $factors,
        ];
    }
}
