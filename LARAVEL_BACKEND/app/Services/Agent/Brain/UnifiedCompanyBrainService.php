<?php

namespace App\Services\Agent\Brain;

use App\Models\CommerceAgentEvent;
use App\Models\Company;
use App\Models\CompanyBrainSnapshot;
use App\Models\Order;
use App\Services\Agent\Platform\BusinessHealthScoreService;
use App\Services\Agent\Platform\BusinessWorldModelService;
use App\Services\Growth\GrowthAnalyticsService;
use Illuminate\Support\Facades\Log;

/**
 * Unified company brain — bridges Commerce OS + Growth Engine into one context.
 */
final class UnifiedCompanyBrainService
{
    public function __construct(
        protected BusinessWorldModelService $worldModel,
        protected GrowthAnalyticsService $growthAnalytics,
        protected BusinessHealthScoreService $healthScore,
    ) {}

    public function buildSnapshot(Company $company): CompanyBrainSnapshot
    {
        $company->loadMissing('settings');
        $companyId = (int) $company->id;

        $commerce = $this->worldModel->build($company);
        $growth = [
            'executive_summary' => $this->growthAnalytics->executiveSummary($companyId, '30d'),
            'platform_breakdown' => $this->growthAnalytics->platformBreakdown($companyId, '30d'),
            'top_posts' => $this->growthAnalytics->topPerformingPosts($companyId, '30d', 5),
        ];

        $revenue7 = (float) Order::where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('total');
        $revenuePrev7 = (float) Order::where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->sum('total');

        $openEvents = CommerceAgentEvent::where('company_id', $companyId)
            ->where('status', 'open')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['event_type', 'event_key', 'payload'])
            ->map(fn ($e) => [
                'type' => $e->event_type,
                'key' => $e->event_key,
                'payload' => $e->payload,
            ])
            ->all();

        $digest = [
            'revenue_7d' => $revenue7,
            'revenue_prev_7d' => $revenuePrev7,
            'revenue_change_pct' => $revenuePrev7 > 0
                ? round((($revenue7 - $revenuePrev7) / $revenuePrev7) * 100, 1)
                : null,
            'open_agent_events' => count($openEvents),
            'health' => $this->healthScore->computeForCompany($company)->only(['overall_score', 'factors', 'summary']),
        ];

        $summary = $this->buildSummaryText($commerce, $growth, $digest);

        return CompanyBrainSnapshot::create([
            'company_id' => $companyId,
            'snapshot_at' => now(),
            'commerce_data' => $commerce,
            'growth_data' => $growth,
            'digest' => array_merge($digest, ['open_events' => $openEvents]),
            'summary_text' => $summary,
        ]);
    }

    public function getLatestSnapshot(Company $company): ?CompanyBrainSnapshot
    {
        return CompanyBrainSnapshot::where('company_id', $company->id)
            ->orderByDesc('snapshot_at')
            ->first();
    }

    public function getForPrompt(Company $company): string
    {
        if (! config('agent.brain.enabled', true)) {
            return '';
        }

        $snapshot = $this->getLatestSnapshot($company);
        if (! $snapshot || ! $snapshot->summary_text) {
            return '';
        }

        $age = $snapshot->snapshot_at?->diffForHumans() ?? 'recently';

        return "Unified company brain (updated {$age}):\n".$snapshot->summary_text;
    }

    public function refreshIfStale(Company $company, int $maxAgeMinutes = 60): ?CompanyBrainSnapshot
    {
        $latest = $this->getLatestSnapshot($company);
        if ($latest && $latest->snapshot_at && $latest->snapshot_at->gte(now()->subMinutes($maxAgeMinutes))) {
            return $latest;
        }

        try {
            return $this->buildSnapshot($company);
        } catch (\Throwable $e) {
            Log::warning('Unified brain snapshot failed', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return $latest;
        }
    }

    /**
     * @param  array<string, mixed>  $commerce
     * @param  array<string, mixed>  $growth
     * @param  array<string, mixed>  $digest
     */
    private function buildSummaryText(array $commerce, array $growth, array $digest): string
    {
        $lines = [];
        $summary = $growth['executive_summary'] ?? [];
        $lines[] = sprintf(
            'Commerce: %d paid orders (30d), revenue %.0f; %d pending payments.',
            (int) ($commerce['orders']['paid_last_30_days'] ?? 0),
            (float) ($commerce['orders']['revenue_last_30_days'] ?? 0),
            (int) ($commerce['orders']['pending_payment'] ?? 0),
        );
        $lines[] = sprintf(
            'Growth: %d leads, ad spend %.0f, ROI %s%%.',
            (int) ($summary['leads'] ?? 0),
            (float) ($summary['adSpend'] ?? 0),
            $summary['roi'] !== null ? (string) $summary['roi'] : 'n/a',
        );
        if ($digest['revenue_change_pct'] !== null) {
            $lines[] = sprintf('Revenue trend (7d vs prior 7d): %+.1f%%.', $digest['revenue_change_pct']);
        }
        if (($digest['open_agent_events'] ?? 0) > 0) {
            $lines[] = sprintf('%d open commerce agent events need attention.', $digest['open_agent_events']);
        }

        return implode(' ', $lines);
    }
}
