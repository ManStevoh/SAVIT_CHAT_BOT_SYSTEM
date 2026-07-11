<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Company;
use App\Models\ExecutivePlan;

/**
 * Executive planning (#47) — break owner goals into KPIs and work streams.
 */
final class ExecutivePlanningService
{
    /**
     * @return array{plan: ExecutivePlan, breakdown: array<string, mixed>}
     */
    public function createPlan(Company $company, string $goalStatement): array
    {
        $breakdown = $this->planBreakdown($goalStatement);
        $kpiTargets = $breakdown['kpi_targets'] ?? [];

        $plan = ExecutivePlan::create([
            'company_id' => $company->id,
            'goal_statement' => mb_substr($goalStatement, 0, 500),
            'breakdown' => $breakdown,
            'kpi_targets' => $kpiTargets,
            'status' => 'active',
        ]);

        return ['plan' => $plan, 'breakdown' => $breakdown];
    }

    /**
     * Plan breakdown without persisting — used by Intelligence API.
     *
     * @return array<string, mixed>
     */
    public function planBreakdown(string $goal): array
    {
        return $this->breakdownForGoal($goal);
    }

    /**
     * @return array<string, mixed>
     */
    private function breakdownForGoal(string $goal): array
    {
        $lower = mb_strtolower($goal);

        if (str_contains($lower, 'revenue') || str_contains($lower, 'sales') || str_contains($lower, 'double')) {
            return [
                'streams' => [
                    ['agent' => 'marketing_director', 'work' => 'Increase traffic and campaign reach'],
                    ['agent' => 'sales_director', 'work' => 'Improve conversion and follow-up speed'],
                    ['agent' => 'support_director', 'work' => 'Reduce refunds and increase satisfaction'],
                    ['agent' => 'inventory_director', 'work' => 'Promote slow movers and prevent stockouts'],
                ],
                'kpi_targets' => [
                    'revenue_growth_pct' => 100,
                    'conversion_rate' => 'track weekly',
                    'repeat_purchase_rate' => 'track monthly',
                    'average_order_value' => 'track weekly',
                    'refund_rate' => 'reduce',
                ],
            ];
        }

        return [
            'streams' => [
                ['agent' => 'ceo', 'work' => 'Clarify goal metrics and assign directors'],
            ],
            'kpi_targets' => ['custom_goal' => $goal],
        ];
    }
}
