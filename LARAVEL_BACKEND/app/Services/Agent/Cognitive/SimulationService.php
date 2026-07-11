<?php

namespace App\Services\Agent\Cognitive;

use App\Models\CognitiveSimulation;
use App\Models\Company;

/**
 * Continuous simulation (#54) — compare scenarios before major recommendations.
 */
final class SimulationService
{
    /**
     * @param  array<string, mixed>  $inputs
     * @return array{simulation: CognitiveSimulation, scenarios: list<array<string, mixed>>, recommendation: string}
     */
    public function simulate(Company $company, string $scenarioType, array $inputs): array
    {
        $scenarios = match ($scenarioType) {
            'discount' => $this->discountScenarios($inputs),
            'marketing_campaign' => $this->campaignScenarios($inputs),
            default => $this->genericScenarios($inputs),
        };

        $recommendation = $this->pickRecommendation($scenarios);

        $simulation = CognitiveSimulation::create([
            'company_id' => $company->id,
            'scenario_type' => $scenarioType,
            'inputs' => $inputs,
            'scenarios' => $scenarios,
            'recommendation' => $recommendation,
        ]);

        return [
            'simulation' => $simulation,
            'scenarios' => $scenarios,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<array<string, mixed>>
     */
    private function discountScenarios(array $inputs): array
    {
        $pct = (float) ($inputs['discount_pct'] ?? 10);

        return [
            [
                'label' => "{$pct}% discount",
                'estimated_revenue_change' => '+'.min(15, $pct).'%',
                'estimated_profit_change' => '-'.round($pct * 0.3, 1).'%',
                'repeat_customers' => '+'.min(20, $pct * 1.5).'%',
                'inventory_clear_days' => max(5, (int) (14 - $pct / 2)),
            ],
            [
                'label' => 'Bundle instead of discount',
                'estimated_revenue_change' => '+8%',
                'estimated_profit_change' => '-1%',
                'repeat_customers' => '+12%',
                'inventory_clear_days' => 12,
            ],
            [
                'label' => 'No discount — value explanation',
                'estimated_revenue_change' => '0%',
                'estimated_profit_change' => '0%',
                'repeat_customers' => '+3%',
                'inventory_clear_days' => 20,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<array<string, mixed>>
     */
    private function campaignScenarios(array $inputs): array
    {
        return [
            ['label' => 'WhatsApp broadcast', 'estimated_reach' => 'high', 'estimated_cost' => 'low'],
            ['label' => 'SMS follow-up', 'estimated_reach' => 'medium', 'estimated_cost' => 'medium'],
            ['label' => 'No campaign', 'estimated_reach' => 'none', 'estimated_cost' => 'none'],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<array<string, mixed>>
     */
    private function genericScenarios(array $inputs): array
    {
        return [
            ['label' => 'Option A', 'note' => 'Proceed with conservative approach', 'inputs' => $inputs],
            ['label' => 'Option B', 'note' => 'Proceed with aggressive approach', 'inputs' => $inputs],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $scenarios
     */
    private function pickRecommendation(array $scenarios): string
    {
        if (count($scenarios) >= 2 && isset($scenarios[1]['label'])) {
            return 'Consider "'.$scenarios[1]['label'].'" — better profit preservation than deep discount.';
        }

        return 'Review scenarios with owner before executing.';
    }
}
