<?php

namespace App\Services\Agent\Platform;

use App\Models\BusinessOpportunity;
use App\Models\CommerceBrief;
use App\Models\Company;
use App\Models\OrganizationalMemory;

/**
 * Executive AI — top decisions with explainable evidence (#26, #35).
 */
final class ExecutiveBriefService
{
    public function __construct(
        protected BusinessWorldModelService $worldModel,
        protected BusinessHealthScoreService $healthScore,
    ) {}

    /**
     * @return list<array{decision: string, evidence: array<string, mixed>, risk: string, requires_approval: bool}>
     */
    public function topDecisionsForCompany(Company $company, int $limit = 3): array
    {
        $decisions = [];
        $world = $this->worldModel->build($company);
        $health = $this->healthScore->computeForCompany($company);

        $pending = (int) ($world['orders']['pending_payment'] ?? 0);
        if ($pending > 0) {
            $decisions[] = [
                'decision' => "Follow up on {$pending} unpaid order(s) today",
                'evidence' => ['pending_payments' => $pending, 'source' => 'world_model.orders'],
                'risk' => 'low',
                'requires_approval' => false,
            ];
        }

        $opportunities = BusinessOpportunity::query()
            ->where('company_id', $company->id)
            ->where('status', 'open')
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->limit(5)
            ->get();

        foreach ($opportunities as $opp) {
            if (count($decisions) >= $limit) {
                break;
            }
            $decisions[] = [
                'decision' => $opp->title,
                'evidence' => $opp->evidence ?? ['description' => $opp->description],
                'risk' => $opp->opportunity_type === 'clear_inventory' ? 'medium' : 'low',
                'requires_approval' => $opp->opportunity_type === 'clear_inventory',
            ];
        }

        if (count($decisions) < $limit && ($health->overall_score ?? 100) < 60) {
            $decisions[] = [
                'decision' => 'Review business health drivers — score below 60',
                'evidence' => ['health_score' => $health->overall_score, 'factors' => $health->factors],
                'risk' => 'low',
                'requires_approval' => false,
            ];
        }

        return array_slice($decisions, 0, $limit);
    }

    public function attachToBrief(CommerceBrief $brief, Company $company): CommerceBrief
    {
        $brief->update([
            'executive_decisions' => $this->topDecisionsForCompany($company),
        ]);

        return $brief->fresh();
    }
}
