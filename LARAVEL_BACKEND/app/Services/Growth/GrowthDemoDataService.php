<?php

namespace App\Services\Growth;

use App\Models\AttributionEvent;
use App\Models\Company;

class GrowthDemoDataService
{
    public function shouldUseDemo(Company $company, array $summary): bool
    {
        if (! $company->growth_demo_mode && ! $company->growth_pilot_at) {
            return false;
        }

        $hasRealData = ($summary['clicks'] ?? 0) > 0
            || ($summary['leads'] ?? 0) > 0
            || ($summary['revenue'] ?? 0) > 0;

        if ($hasRealData) {
            return false;
        }

        return ! AttributionEvent::where('company_id', $company->id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function demoAnalytics(string $period = '30d'): array
    {
        return [
            'isDemo' => true,
            'summary' => [
                'period' => $period,
                'leads' => 12,
                'whatsappStarts' => 18,
                'clicks' => 47,
                'orders' => 5,
                'revenue' => 24500.0,
                'adSpend' => 3200.0,
                'conversionRate' => 38.3,
                'leadToOrderRate' => 41.7,
                'costPerLead' => 266.67,
                'customerAcquisitionCost' => 640.0,
                'roi' => 665.6,
            ],
            'platformBreakdown' => [
                ['platform' => 'instagram', 'orders' => 3, 'revenue' => 15200.0, 'leads' => 8],
                ['platform' => 'facebook', 'orders' => 2, 'revenue' => 9300.0, 'leads' => 4],
            ],
            'topPosts' => [
                [
                    'id' => 'demo-1',
                    'title' => 'Weekend special — order on WhatsApp',
                    'platform' => 'instagram',
                    'clicks' => 28,
                    'leads' => 7,
                    'revenue' => 15200.0,
                    'performanceScore' => 82.5,
                ],
                [
                    'id' => 'demo-2',
                    'title' => 'New arrivals — tap to chat',
                    'platform' => 'facebook',
                    'clicks' => 19,
                    'leads' => 5,
                    'revenue' => 9300.0,
                    'performanceScore' => 71.0,
                ],
            ],
            'funnel' => [
                ['stage' => 'Clicks', 'value' => 47],
                ['stage' => 'WhatsApp', 'value' => 18],
                ['stage' => 'Leads', 'value' => 12],
                ['stage' => 'Orders', 'value' => 5],
                ['stage' => 'Revenue', 'value' => 24500],
            ],
        ];
    }
}
