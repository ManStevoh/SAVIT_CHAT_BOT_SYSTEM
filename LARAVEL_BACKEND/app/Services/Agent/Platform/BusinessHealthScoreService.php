<?php

namespace App\Services\Agent\Platform;

use App\Models\BusinessHealthScore;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;

/**
 * Single evolving business health score (#30).
 */
final class BusinessHealthScoreService
{
    public function computeForCompany(Company $company): BusinessHealthScore
    {
        $companyId = (int) $company->id;
        $date = now()->toDateString();

        $existing = BusinessHealthScore::where('company_id', $companyId)->whereDate('score_date', $date)->first();
        if ($existing) {
            return $existing;
        }

        $revenue7 = (float) Order::where('company_id', $companyId)->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(7))->sum('total');
        $revenuePrev7 = (float) Order::where('company_id', $companyId)->where('payment_status', 'paid')
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->sum('total');

        $pending = Order::where('company_id', $companyId)->where('payment_status', 'pending')->count();
        $frustratedChats = Chat::where('company_id', $companyId)
            ->where('detected_sentiment', 'frustrated')
            ->where('last_message_at', '>=', now()->subDays(7))->count();

        $revenueTrend = $revenuePrev7 > 0 ? round((($revenue7 - $revenuePrev7) / $revenuePrev7) * 100, 1) : ($revenue7 > 0 ? 100 : 0);

        $factors = [
            'revenue_7d' => ['score' => $this->clampScore($revenue7 > 0 ? 70 + min(30, $revenueTrend / 2) : 30), 'value' => $revenue7, 'trend_pct' => $revenueTrend],
            'pending_orders' => ['score' => $this->clampScore(100 - min(50, $pending * 5)), 'value' => $pending],
            'customer_sentiment' => ['score' => $this->clampScore(100 - ($frustratedChats * 15)), 'frustrated_chats_7d' => $frustratedChats],
            'repeat_potential' => ['score' => 65, 'note' => 'Baseline until repeat-rate pipeline matures'],
        ];

        $overall = (int) round(array_sum(array_column($factors, 'score')) / count($factors));

        $summary = $this->buildSummary($overall, $factors);

        return BusinessHealthScore::create([
            'company_id' => $companyId,
            'score_date' => $date,
            'overall_score' => $overall,
            'factors' => $factors,
            'trends' => ['revenue_week_over_week_pct' => $revenueTrend],
            'summary' => $summary,
        ]);
    }

    private function clampScore(float $score): int
    {
        return (int) max(0, min(100, round($score)));
    }

    /**
     * @param  array<string, array<string, mixed>>  $factors
     */
    private function buildSummary(int $overall, array $factors): string
    {
        $revTrend = $factors['revenue_7d']['trend_pct'] ?? 0;
        $pending = $factors['pending_orders']['value'] ?? 0;

        return "Business health {$overall}/100. Revenue trend {$revTrend}% week-over-week. {$pending} pending payment(s).";
    }
}
