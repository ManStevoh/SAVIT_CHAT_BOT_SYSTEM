<?php

namespace App\Services\Agent\Intelligence;

use App\Models\Company;
use App\Models\IntelligenceOutcome;
use App\Models\InvestigationCase;
use App\Models\User;

/**
 * ABI Level 20 — outcome tracking for intelligence recommendations.
 */
final class IntelligenceOutcomeService
{
    public function seedFromReasoning(Company $company, int $investigationId, array $recommendedActions, array $rawRecommendations = []): void
    {
        $actions = $recommendedActions;
        if ($actions === [] && $rawRecommendations !== []) {
            foreach ($rawRecommendations as $rec) {
                $actions[] = ['action' => (string) $rec, 'source' => 'investigation'];
            }
        }

        $this->seedActions($company, 'investigation', $investigationId, $actions);

        if (IntelligenceOutcome::where('company_id', $company->id)->where('source_id', $investigationId)->where('source_type', 'investigation')->count() === 0) {
            IntelligenceOutcome::create([
                'company_id' => $company->id,
                'source_type' => 'investigation',
                'source_id' => $investigationId,
                'recommendation_key' => $this->recommendationKey('review_findings'),
                'recommended_action' => 'Review investigation findings and validate top hypothesis with fresh data.',
                'outcome' => 'pending',
            ]);
        }
    }

    /**
     * @param  list<string>  $recommendations
     */
    public function seedFromBrief(Company $company, int $briefId, array $recommendations): void
    {
        $actions = [];
        foreach ($recommendations as $rec) {
            if (is_string($rec) && trim($rec) !== '') {
                $actions[] = ['action' => trim($rec), 'source' => 'brief'];
            }
        }

        $this->seedActions($company, 'brief', $briefId, $actions);
    }

    public function seedFromOpportunity(Company $company, int $opportunityId, string $title, ?string $description = null): void
    {
        $action = trim($title);
        if ($description) {
            $action .= ' — '.trim($description);
        }

        $this->seedActions($company, 'opportunity', $opportunityId, [
