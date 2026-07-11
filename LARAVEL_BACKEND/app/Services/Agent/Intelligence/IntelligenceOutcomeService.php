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
            ['action' => $action, 'source' => 'opportunity'],
        ]);
    }

    /**
     * @param  list<array{action?: string, source?: string}|string>  $actions
     */
    private function seedActions(Company $company, string $sourceType, int $sourceId, array $actions): void
    {
        foreach (array_slice($actions, 0, 8) as $action) {
            if (is_string($action)) {
                $text = trim($action);
            } elseif (is_array($action) && ! empty($action['action'])) {
                $text = (string) $action['action'];
            } else {
                continue;
            }

            if ($text === '') {
                continue;
            }

            IntelligenceOutcome::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'recommendation_key' => $this->recommendationKey($text),
                ],
                [
                    'recommended_action' => mb_substr($text, 0, 2000),
                    'outcome' => 'pending',
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function record(
        Company $company,
        User $user,
        string $sourceType,
        int $sourceId,
        string $recommendedAction,
        string $outcome,
        ?string $notes = null,
        array $metrics = [],
    ): IntelligenceOutcome {
        $outcome = in_array($outcome, ['positive', 'neutral', 'negative', 'pending'], true)
            ? $outcome
            : 'pending';

        $record = IntelligenceOutcome::updateOrCreate(
            [
                'company_id' => $company->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'recommendation_key' => $this->recommendationKey($recommendedAction),
            ],
            [
                'recommended_action' => mb_substr($recommendedAction, 0, 2000),
                'outcome' => $outcome,
                'notes' => $notes ? mb_substr($notes, 0, 2000) : null,
                'metrics' => $metrics !== [] ? $metrics : null,
                'recorded_by' => $user->id,
                'measured_at' => now(),
            ],
        );

        $case = InvestigationCase::where('company_id', $company->id)
            ->where('owner_analytics_investigation_id', $sourceType === 'investigation' ? $sourceId : null)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($case && $outcome !== 'pending') {
            app(InvestigationCaseService::class)->advanceStep($case, 'outcome_tracking', 'completed');
        }

        return $record;
    }

    private function recommendationKey(string $action): string
    {
        return substr(hash('sha256', mb_strtolower(trim($action))), 0, 16);
    }
}
