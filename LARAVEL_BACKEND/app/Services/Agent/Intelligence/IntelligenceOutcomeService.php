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
