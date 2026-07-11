<?php

namespace App\Services\Agent\Intelligence;

use App\Models\Company;
use App\Models\InvestigationCase;
use App\Models\OwnerAnalyticsInvestigation;

/**
 * ABI Levels 2–3 — persistent multi-step investigation case files.
 */
final class InvestigationCaseService
{
    /**
     * @param  array<string, mixed>  $reasoningResult
     */
    public function openFromReasoning(
        Company $company,
        OwnerAnalyticsInvestigation $investigation,
        string $goal,
        array $reasoningResult,
    ): InvestigationCase {
        $steps = [
            ['step' => 1, 'name' => 'evidence_gathered', 'status' => 'completed', 'at' => now()->toIso8601String()],
            ['step' => 2, 'name' => 'analysis_completed', 'status' => 'completed', 'at' => now()->toIso8601String()],
            ['step' => 3, 'name' => 'actions_recommended', 'status' => 'completed', 'at' => now()->toIso8601String()],
            ['step' => 4, 'name' => 'outcome_tracking', 'status' => 'pending', 'at' => null],
        ];

        return InvestigationCase::create([
            'company_id' => $company->id,
            'owner_analytics_investigation_id' => $investigation->id,
            'goal' => mb_substr(trim($goal), 0, 1000),
            'status' => 'open',
            'current_step' => 4,
            'steps' => $steps,
            'metadata' => [
                'confidence' => $reasoningResult['confidence'] ?? null,
                'hypothesis_count' => count($reasoningResult['hypotheses'] ?? []),
                'action_count' => count($reasoningResult['recommended_actions'] ?? []),
                'simulation_id' => $reasoningResult['simulation']['id'] ?? null,
            ],
        ]);
    }

    public function advanceStep(InvestigationCase $case, string $stepName, string $status = 'completed'): InvestigationCase
    {
        $steps = $case->steps ?? [];
        foreach ($steps as $i => $step) {
            if (($step['name'] ?? '') === $stepName) {
                $steps[$i]['status'] = $status;
                $steps[$i]['at'] = now()->toIso8601String();
                $case->current_step = max($case->current_step, (int) ($step['step'] ?? $case->current_step));
            }
        }

        if ($stepName === 'outcome_tracking' && $status === 'completed') {
            $case->status = 'closed';
            $case->closed_at = now();
        }

        $case->steps = $steps;
        $case->save();

        return $case->fresh();
    }

    /**
     * @return array{case: InvestigationCase, investigation: OwnerAnalyticsInvestigation|null}
     */
    public function showForCompany(Company $company, int $caseId): ?array
    {
        $case = InvestigationCase::where('company_id', $company->id)
            ->with('investigation')
            ->find($caseId);

        if (! $case) {
            return null;
        }

        return [
            'case' => $case,
            'investigation' => $case->investigation,
        ];
    }
}
