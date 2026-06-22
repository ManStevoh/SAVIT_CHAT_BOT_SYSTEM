<?php

namespace App\Services\Growth;

use App\Models\AttributionEvent;
use App\Models\Company;

class GrowthBenchmarkService
{
    /**
     * @return array{percentile: ?int, leadToOrderRate: float, portfolioMedian: float, message: string}
     */
    public function leadToOrderBenchmark(Company $company, string $period = '30d'): array
    {
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
        $since = now()->subDays($days);

        $companyLeads = AttributionEvent::where('company_id', $company->id)
            ->where('event_type', 'lead')
            ->where('created_at', '>=', $since)
            ->count();
        $companyOrders = AttributionEvent::where('company_id', $company->id)
            ->where('event_type', 'order')
            ->where('created_at', '>=', $since)
            ->count();

        $companyRate = $companyLeads > 0 ? ($companyOrders / $companyLeads) * 100 : 0;

        $rates = Company::where('status', 'active')
            ->where('id', '!=', $company->id)
            ->get()
            ->map(function (Company $c) use ($since) {
                $leads = AttributionEvent::where('company_id', $c->id)
                    ->where('event_type', 'lead')
                    ->where('created_at', '>=', $since)
                    ->count();
                if ($leads === 0) {
                    return null;
                }
                $orders = AttributionEvent::where('company_id', $c->id)
                    ->where('event_type', 'order')
                    ->where('created_at', '>=', $since)
                    ->count();

                return ($orders / $leads) * 100;
            })
            ->filter()
            ->sort()
            ->values();

        if ($rates->isEmpty() || $companyLeads === 0) {
            return [
                'percentile' => null,
                'leadToOrderRate' => round($companyRate, 1),
                'portfolioMedian' => 0,
                'message' => 'Benchmarks unlock when portfolio brands have attributed leads.',
            ];
        }

        $below = $rates->filter(fn ($r) => $r < $companyRate)->count();
        $percentile = (int) round(($below / $rates->count()) * 100);
        $median = $rates->median();

        return [
            'percentile' => $percentile,
            'leadToOrderRate' => round($companyRate, 1),
            'portfolioMedian' => round((float) $median, 1),
            'message' => "You're at the {$percentile}th percentile on lead→order vs the portfolio.",
        ];
    }
}
