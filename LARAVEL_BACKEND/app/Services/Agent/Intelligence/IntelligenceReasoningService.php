<?php

namespace App\Services\Agent\Intelligence;

use App\Models\Company;
use App\Services\Agent\Cognitive\CausalReasoningService;
use App\Services\Agent\Cognitive\ExecutivePlanningService;
use App\Services\Agent\Cognitive\ForecastService;
use App\Services\Agent\Cognitive\SimulationService;
use App\Services\Agent\Intelligence\BusinessProbabilityService;
use App\Services\Agent\Intelligence\IntelligenceOutcomeService;
use App\Services\Agent\Intelligence\InvestigationCaseService;
use App\Services\Agent\Owner\OwnerAnalyticsAgentService;
use App\Services\Agent\Platform\BusinessHealthScoreService;
use App\Services\Agent\Platform\BusinessWorldModelService;
use App\Services\Agent\Platform\ExecutiveBriefService;
use App\Services\Platform\DomainEventDispatcher;
use App\Services\Platform\NotificationDispatcher;

/**
 * ABI Level 19 — unified decision intelligence: goal → evidence → hypotheses → scenarios → actions.
 */
final class IntelligenceReasoningService
{
    public function __construct(
        protected BusinessWorldModelService $worldModel,
        protected BusinessHealthScoreService $healthScore,
        protected CausalReasoningService $causal,
        protected ForecastService $forecast,
        protected ExecutiveBriefService $executive,
        protected OwnerAnalyticsAgentService $ownerAnalytics,
        protected ExecutivePlanningService $planning,
        protected SimulationService $simulation,
        protected InvestigationCaseService $cases,
        protected IntelligenceOutcomeService $outcomes,
        protected BusinessProbabilityService $probabilities,
        protected DomainEventDispatcher $events,
        protected NotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array{
     *   goal: string,
     *   period?: string,
     *   time_horizon?: string,
     *   constraints?: list<string>,
     *   context?: array<string, mixed>,
     *   simulate?: bool,
     *   scenario_type?: string,
     *   scenario_inputs?: array<string, mixed>,
     *   include_plan?: bool,
     *   persist_plan?: bool,
     *   persist_investigation?: bool,
     * }  $input
     * @return array<string, mixed>
     */
    public function reason(Company $company, array $input): array
    {
        $company->loadMissing('settings');
        $goal = trim((string) ($input['goal'] ?? ''));
        $period = (string) ($input['period'] ?? '30d');
        $constraints = is_array($input['constraints'] ?? null) ? $input['constraints'] : [];
        $timeHorizon = (string) ($input['time_horizon'] ?? $period);
        $context = is_array($input['context'] ?? null) ? $input['context'] : [];

        $world = $this->worldModel->build($company);
        $causal = $this->causal->analyzeSalesChange($company);
        $health = $this->healthScore->computeForCompany($company);
        $forecast = $this->forecast->demandForecast($company);
        $executiveDecisions = $this->executive->topDecisionsForCompany($company, 5);

        $investigation = $this->ownerAnalytics->investigate($company, $goal, $period);
        $evidence = $investigation->evidence ?? [];
        $findings = $investigation->findings ?? [];
        $recommendations = $investigation->recommendations ?? [];

        $hypotheses = $this->buildHypotheses($causal, $findings);
        $assumptions = $this->buildAssumptions($company, $constraints, $timeHorizon, $world);
        $missingInfo = $this->detectMissingInfo($goal, $world, $evidence);

        $simulation = null;
        if (($input['simulate'] ?? false) || $this->shouldAutoSimulate($goal)) {
            $scenarioType = (string) ($input['scenario_type'] ?? $this->inferScenarioType($goal));
            $scenarioInputs = is_array($input['scenario_inputs'] ?? null)
                ? $input['scenario_inputs']
                : $this->inferScenarioInputs($goal, $scenarioType);
            $simulation = $this->simulation->simulate($company, $scenarioType, $scenarioInputs);
        }

        $plan = null;
        if (($input['include_plan'] ?? false) || $this->shouldAutoPlan($goal)) {
            if (! empty($input['persist_plan'])) {
                $created = $this->planning->createPlan($company, $goal);
                $plan = [
                    'id' => $created['plan']->id,
                    'goal_statement' => $created['plan']->goal_statement,
                    'breakdown' => $created['breakdown'],
                    'persisted' => true,
                ];
            } else {
                $plan = [
                    'breakdown' => $this->planning->planBreakdown($goal),
                    'persisted' => false,
                ];
            }
        }

        $recommendedActions = $this->mergeActions(
            $recommendations,
            $executiveDecisions,
            $simulation['recommendation'] ?? null,
        );

        $probabilities = $this->probabilities->computeForCompany($company);

        $confidence = (float) ($investigation->confidence ?? 0.55);
        if ($simulation !== null) {
            $confidence = min(0.95, $confidence + 0.05);
        }
        if ($missingInfo !== []) {
            $confidence = max(0.35, $confidence - 0.1);
        }

        $result = [
            'goal' => $goal,
            'period' => $period,
            'time_horizon' => $timeHorizon,
            'confidence' => round($confidence, 2),
            'executive_summary' => $this->executiveSummary($findings, $causal, $confidence),
            'assumptions' => $assumptions,
            'evidence' => $evidence,
            'world_model_snapshot' => [
                'orders' => $world['orders'] ?? null,
                'customers' => $world['customers'] ?? null,
                'inventory' => $world['inventory'] ?? null,
            ],
            'hypotheses' => $hypotheses,
            'causal_analysis' => $causal,
            'health_score' => [
                'overall' => $health->overall_score,
                'factors' => $health->factors,
                'summary' => $health->summary,
            ],
            'forecast' => $forecast,
            'executive_decisions' => $executiveDecisions,
            'findings' => $findings,
            'recommended_actions' => $recommendedActions,
            'missing_info' => $missingInfo,
            'probability_scores' => $probabilities,
            'simulation' => $simulation ? [
                'id' => $simulation['simulation']->id,
                'scenario_type' => $simulation['simulation']->scenario_type,
                'scenarios' => $simulation['scenarios'],
                'recommendation' => $simulation['recommendation'],
            ] : null,
            'plan' => $plan,
            'investigation_id' => $investigation->id,
            'context' => $context,
            'constraints' => $constraints,
        ];

        $openCase = ($input['open_case'] ?? true) !== false;
        if ($openCase) {
            $case = $this->cases->openFromReasoning($company, $investigation, $goal, $result);
            $result['case_id'] = $case->id;
            $this->notifications->dispatch($company, 'intelligence.case_opened', [
                'goal' => $goal,
                'owner_email' => $company->email,
            ]);
        }

        $this->outcomes->seedFromReasoning($company, (int) $investigation->id, $recommendedActions, $recommendations);

        $this->events->dispatch('intelligence.reasoned', [
            'investigation_id' => $investigation->id,
            'case_id' => $result['case_id'] ?? null,
            'goal' => $goal,
            'confidence' => $result['confidence'],
        ], $company->id);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $causal
     * @param  list<array<string, mixed>>  $findings
     * @return list<array<string, mixed>>
     */
    private function buildHypotheses(array $causal, array $findings): array
    {
        $hypotheses = [];
        foreach ($causal['likely_causes'] ?? [] as $cause) {
            $hypotheses[] = [
                'hypothesis' => (string) ($cause['cause'] ?? 'unknown'),
                'likelihood' => (string) ($cause['likelihood'] ?? 'medium'),
                'source' => 'causal_reasoning',
                'confidence' => $this->likelihoodToScore((string) ($cause['likelihood'] ?? 'medium')),
            ];
        }

        foreach ($findings as $finding) {
            if (! is_array($finding) || empty($finding['claim'])) {
                continue;
            }
            $hypotheses[] = [
                'hypothesis' => (string) $finding['claim'],
                'likelihood' => (string) ($finding['severity'] ?? 'medium'),
                'source' => 'investigation',
                'evidence_key' => $finding['evidence_key'] ?? null,
                'confidence' => $this->severityToScore((string) ($finding['severity'] ?? 'medium')),
            ];
        }

        return $hypotheses;
    }

    /**
     * @param  list<string>  $constraints
     * @param  array<string, mixed>  $world
     * @return list<string>
     */
    private function buildAssumptions(Company $company, array $constraints, string $timeHorizon, array $world): array
    {
        $assumptions = [
            'Analysis uses data available in the SAVIT platform for company #'.$company->id.'.',
            'Time horizon for recommendations: '.$timeHorizon.'.',
            'Industry context: '.($company->industry ?? 'other').'.',
        ];

        if ($constraints !== []) {
            $assumptions[] = 'Owner constraints: '.implode('; ', $constraints);
        }

        $revenue30d = $world['orders']['revenue_30d'] ?? null;
        if ($revenue30d !== null) {
            $assumptions[] = 'Trailing 30-day paid revenue baseline: '.$revenue30d;
        }

        $dna = $company->settings?->business_dna;
        if (is_array($dna) && ! empty($dna['risk_tolerance'])) {
            $assumptions[] = 'Risk tolerance from business DNA: '.$dna['risk_tolerance'];
        }

        return $assumptions;
    }

    /**
     * @param  array<string, mixed>  $world
     * @param  array<string, mixed>  $evidence
     * @return list<string>
     */
    private function detectMissingInfo(string $goal, array $world, array $evidence): array
    {
        $missing = [];
        $lower = mb_strtolower($goal);

        if (str_contains($lower, 'competitor') || str_contains($lower, 'market')) {
            $missing[] = 'Competitor pricing and market share data not connected.';
        }

        if (str_contains($lower, 'hire') || str_contains($lower, 'payroll')) {
            $missing[] = 'Payroll and headcount data not in platform — use constraints for budget.';
        }

        if (str_contains($lower, 'branch') || str_contains($lower, 'location')) {
            $missing[] = 'Geo expansion model requires branch costs and local demand (not fully modeled).';
        }

        if (($world['customers']['active_phones_30d'] ?? 0) < 5) {
            $missing[] = 'Limited customer history — confidence may be lower for churn/LTV estimates.';
        }

        if (($evidence['growth']['clicks'] ?? 0) === 0 && str_contains($lower, 'ads')) {
            $missing[] = 'No attributed marketing clicks in period — connect Growth Engine for ad ROI.';
        }

        return $missing;
    }

    /**
     * @param  list<string>  $recommendations
     * @param  list<array<string, mixed>>  $decisions
     * @return list<array<string, mixed>>
     */
    private function mergeActions(array $recommendations, array $decisions, ?string $simulationRec): array
    {
        $actions = [];

        foreach ($recommendations as $rec) {
            $actions[] = [
                'action' => (string) $rec,
                'source' => 'investigation',
                'requires_approval' => false,
            ];
        }

        foreach ($decisions as $decision) {
            $actions[] = [
                'action' => (string) ($decision['decision'] ?? ''),
                'source' => 'executive_decisions',
                'requires_approval' => (bool) ($decision['requires_approval'] ?? false),
                'evidence' => $decision['evidence'] ?? null,
            ];
        }

        if ($simulationRec) {
            $actions[] = [
                'action' => $simulationRec,
                'source' => 'simulation',
                'requires_approval' => false,
            ];
        }

        $seen = [];
        $unique = [];
        foreach ($actions as $action) {
            $key = $action['action'];
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $action;
        }

        return array_slice($unique, 0, 12);
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @param  array<string, mixed>  $causal
     */
    private function executiveSummary(array $findings, array $causal, float $confidence): string
    {
        $parts = [];
        $change = $causal['change'] ?? 'unknown';
        if ($change !== 'unknown') {
            $parts[] = 'Revenue trend (14d): '.$change.'.';
        }

        foreach (array_slice($findings, 0, 2) as $f) {
            if (is_array($f) && ! empty($f['claim'])) {
                $parts[] = (string) $f['claim'];
            }
        }

        if ($parts === []) {
            return 'Analysis complete. Review hypotheses, scenarios, and recommended actions below.';
        }

        $parts[] = 'Confidence: '.round($confidence * 100).'%';

        return implode(' ', $parts);
    }

    private function shouldAutoSimulate(string $goal): bool
    {
        $lower = mb_strtolower($goal);

        return str_contains($lower, 'discount')
            || str_contains($lower, 'campaign')
            || str_contains($lower, 'ads')
            || str_contains($lower, 'facebook')
            || str_contains($lower, 'hire')
            || str_contains($lower, 'spend')
            || str_contains($lower, 'branch');
    }

    private function shouldAutoPlan(string $goal): bool
    {
        $lower = mb_strtolower($goal);

        return str_contains($lower, 'double')
            || str_contains($lower, 'revenue')
            || str_contains($lower, 'grow')
            || str_contains($lower, 'expand')
            || str_contains($lower, 'hire')
            || str_contains($lower, 'plan');
    }

    private function inferScenarioType(string $goal): string
    {
        $lower = mb_strtolower($goal);
        if (str_contains($lower, 'discount') || str_contains($lower, 'price')) {
            return 'discount';
        }
        if (str_contains($lower, 'campaign') || str_contains($lower, 'ads') || str_contains($lower, 'facebook') || str_contains($lower, 'marketing')) {
            return 'marketing_campaign';
        }

        return 'generic';
    }

    /**
     * @return array<string, mixed>
     */
    private function inferScenarioInputs(string $goal, string $scenarioType): array
    {
        if ($scenarioType === 'discount' && preg_match('/(\d+)\s*%/', $goal, $m)) {
            return ['discount_pct' => (int) $m[1]];
        }

        return [];
    }

    private function likelihoodToScore(string $likelihood): float
    {
        return match ($likelihood) {
            'high' => 0.8,
            'medium' => 0.55,
            'low' => 0.3,
            default => 0.5,
        };
    }

    private function severityToScore(string $severity): float
    {
        return match ($severity) {
            'high' => 0.85,
            'medium' => 0.6,
            'low' => 0.4,
            'info' => 0.5,
            default => 0.5,
        };
    }
}
