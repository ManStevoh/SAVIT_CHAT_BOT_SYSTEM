<?php

namespace App\Services\Agent\Owner;

use App\Models\CommerceAgentEvent;
use App\Models\Company;
use App\Models\Order;
use App\Models\OwnerAnalyticsInvestigation;
use App\Models\Product;
use App\Services\Agent\Brain\UnifiedCompanyBrainService;
use App\Services\Agent\Graph\BusinessGraphV2Service;
use App\Services\Agent\Timeline\BusinessTimelineService;
use App\Services\Agent\Platform\BusinessHealthScoreService;
use App\Services\AI\AiDriverFactory;
use App\Services\AI\AiModelResolver;
use App\Services\AI\Drivers\OpenAiDriver;
use App\Services\Growth\GrowthAnalyticsService;
use App\Models\AiModel;
use Illuminate\Support\Facades\Log;

/**
 * Owner-facing analytics agent — investigates business questions with evidence.
 */
final class OwnerAnalyticsAgentService
{
    public function __construct(
        protected GrowthAnalyticsService $growthAnalytics,
        protected UnifiedCompanyBrainService $brain,
        protected BusinessHealthScoreService $healthScore,
        protected AiModelResolver $resolver,
        protected AiDriverFactory $driverFactory,
        protected BusinessTimelineService $timeline,
        protected BusinessGraphV2Service $graph,
    ) {}

    public function investigate(Company $company, string $question, string $period = '30d'): OwnerAnalyticsInvestigation
    {
        $company->loadMissing('settings');
        $evidence = $this->gatherEvidence($company, $period);
        $ruleFindings = $this->ruleBasedFindings($evidence, $question);

        $llmResult = $this->synthesizeWithLlm($company, $question, $evidence, $ruleFindings);

        $investigation = OwnerAnalyticsInvestigation::create([
            'company_id' => $company->id,
            'question' => mb_substr(trim($question), 0, 500),
            'period' => $period,
            'status' => 'completed',
            'evidence' => $evidence,
            'findings' => $llmResult['findings'] ?? $ruleFindings,
            'recommendations' => $llmResult['recommendations'] ?? [],
            'confidence' => (float) ($llmResult['confidence'] ?? 0.6),
            'model_used' => $llmResult['model'] ?? null,
        ]);

        $this->timeline->record(
            $company,
            'investigation',
            'Owner asked: '.mb_substr($investigation->question, 0, 80),
            is_array($investigation->findings)
                ? implode(' ', array_slice(array_column($investigation->findings, 'claim'), 0, 2))
                : null,
            ['question' => $investigation->question, 'confidence' => $investigation->confidence],
            'owner_analytics_investigation',
            (int) $investigation->id,
            70,
            $investigation->created_at,
            'consciousness',
        );

        return $investigation;
    }

    /**
     * @return array<string, mixed>
     */
    public function gatherEvidence(Company $company, string $period): array
    {
        $companyId = (int) $company->id;
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        $since = now()->subDays($days);
        $priorSince = now()->subDays($days * 2);
        $priorUntil = $since->copy();

        $ordersCurrent = Order::where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $since);
        $ordersPrior = Order::where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $priorSince)
            ->where('created_at', '<', $priorUntil);

        $revenueCurrent = (float) (clone $ordersCurrent)->sum('total');
        $revenuePrior = (float) (clone $ordersPrior)->sum('total');
        $countCurrent = (int) (clone $ordersCurrent)->count();
        $countPrior = (int) (clone $ordersPrior)->count();

        $pendingOrders = Order::where('company_id', $companyId)
            ->where('payment_status', 'pending')
            ->where('created_at', '>=', $since)
            ->count();

        $lowStock = Product::where('company_id', $companyId)
            ->where('status', 'active')
            ->where('stock', '<=', (int) config('agent.company.low_stock_threshold', 5))
            ->orderBy('stock')
            ->limit(10)
            ->get(['name', 'stock'])
            ->map(fn ($p) => ['name' => $p->name, 'stock' => $p->stock])
            ->all();

        $growth = $this->growthAnalytics->executiveSummary($companyId, $period);
        $platforms = $this->growthAnalytics->platformBreakdown($companyId, $period);
        $health = $this->healthScore->computeForCompany($company);

        $brain = $this->brain->refreshIfStale($company, 120);

        $events = CommerceAgentEvent::where('company_id', $companyId)
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->limit(15)
            ->get(['event_type', 'event_key', 'status', 'payload'])
            ->map(fn ($e) => [
                'type' => $e->event_type,
                'status' => $e->status,
                'payload' => $e->payload,
            ])
            ->all();

        $timeline = $this->timeline->timeline($company, 10);
        $graph = $this->graph->exportGraph($company, 40);

        return [
            'period' => $period,
            'orders' => [
                'paid_count_current' => $countCurrent,
                'paid_count_prior' => $countPrior,
                'revenue_current' => $revenueCurrent,
                'revenue_prior' => $revenuePrior,
                'revenue_change_pct' => $revenuePrior > 0
                    ? round((($revenueCurrent - $revenuePrior) / $revenuePrior) * 100, 1)
                    : null,
                'pending_payment_count' => $pendingOrders,
            ],
            'inventory' => ['low_stock_products' => $lowStock],
            'growth' => $growth,
            'platform_breakdown' => $platforms,
            'health_score' => $health->only(['overall_score', 'factors', 'summary']),
            'brain_digest' => $brain?->digest,
            'agent_events' => $events,
            'business_timeline' => $timeline,
            'business_graph' => [
                'stats' => $graph['stats'],
                'sample_nodes' => array_slice($graph['nodes'], 0, 15),
                'sample_edges' => array_slice($graph['edges'], 0, 20),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return list<array{claim: string, evidence_key: string, severity: string}>
     */
    private function ruleBasedFindings(array $evidence, string $question): array
    {
        $findings = [];
        $orders = $evidence['orders'] ?? [];
        $change = $orders['revenue_change_pct'] ?? null;

        if ($change !== null && $change < -10) {
            $findings[] = [
                'claim' => sprintf('Revenue declined %.1f%% vs prior period.', $change),
                'evidence_key' => 'orders.revenue_change_pct',
                'severity' => 'high',
            ];
        }

        $growth = $evidence['growth'] ?? [];
        if (($growth['conversionRate'] ?? 100) < 5 && ($growth['clicks'] ?? 0) > 10) {
            $findings[] = [
                'claim' => 'Marketing clicks are not converting to WhatsApp conversations.',
                'evidence_key' => 'growth.conversionRate',
                'severity' => 'medium',
            ];
        }

        if (($growth['adSpend'] ?? 0) > 0 && ($growth['roi'] ?? 0) < 0) {
            $findings[] = [
                'claim' => 'Ad spend exceeds attributed revenue (negative ROI).',
                'evidence_key' => 'growth.roi',
                'severity' => 'high',
            ];
        }

        $lowStock = $evidence['inventory']['low_stock_products'] ?? [];
        if (count($lowStock) >= 3) {
            $findings[] = [
                'claim' => count($lowStock).' products are low on stock, which may limit sales.',
                'evidence_key' => 'inventory.low_stock_products',
                'severity' => 'medium',
            ];
        }

        if (stripos($question, 'sales') !== false && $findings === [] && $change !== null && $change >= 0) {
            $findings[] = [
                'claim' => 'Sales revenue is stable or growing in the current period.',
                'evidence_key' => 'orders.revenue_change_pct',
                'severity' => 'low',
            ];
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  list<array<string, string>>  $ruleFindings
     * @return array{findings?: list<array<string, mixed>>, recommendations?: list<string>, confidence?: float, model?: string}
     */
    private function synthesizeWithLlm(
        Company $company,
        string $question,
        array $evidence,
        array $ruleFindings,
    ): array {
        if (! config('agent.owner_analytics.use_llm', true)) {
            return [
                'findings' => $ruleFindings,
                'recommendations' => $this->defaultRecommendations($ruleFindings),
                'confidence' => 0.55,
            ];
        }

        $resolved = $this->resolver->resolve($company, AiModel::CAPABILITY_CHAT);
        if ($resolved === null) {
            return [
                'findings' => $ruleFindings,
                'recommendations' => $this->defaultRecommendations($ruleFindings),
                'confidence' => 0.5,
            ];
        }

        $driver = $this->driverFactory->driverFor($resolved->provider);
        if (! $driver instanceof OpenAiDriver) {
            return [
                'findings' => $ruleFindings,
                'recommendations' => $this->defaultRecommendations($ruleFindings),
                'confidence' => 0.5,
            ];
        }

        $prompt = <<<PROMPT
You are the Owner Analytics Agent for "{$company->name}". The owner asked:
"{$question}"

Evidence (JSON):
PROMPT;
        $prompt .= json_encode($evidence, JSON_UNESCAPED_UNICODE);
        $prompt .= "\n\nPreliminary rule findings:\n".json_encode($ruleFindings);
        $prompt .= <<<'PROMPT'

Return JSON only:
{
  "findings": [{"claim": "...", "evidence_key": "orders.revenue_change_pct", "severity": "high|medium|low"}],
  "recommendations": ["actionable step 1", "step 2"],
  "confidence": 0.0,
  "executive_summary": "2-3 sentences for the owner"
}
Cite evidence keys. Be specific with numbers from the evidence. Do not invent data.
PROMPT;

        try {
            $result = $driver->chatCompletion(
                resolved: $resolved,
                messages: [
                    ['role' => 'system', 'content' => 'Owner business analytics. JSON only.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                maxTokens: 900,
                temperature: 0.3,
                jsonMode: true,
                timeoutSeconds: 45,
            );

            if (! $result->success || empty($result->content)) {
                throw new \RuntimeException($result->error ?? 'LLM failed');
            }

            $parsed = json_decode($result->content, true);
            if (! is_array($parsed)) {
                throw new \RuntimeException('Invalid JSON');
            }

            $findings = $parsed['findings'] ?? $ruleFindings;
            if (! empty($parsed['executive_summary'])) {
                array_unshift($findings, [
                    'claim' => (string) $parsed['executive_summary'],
                    'evidence_key' => 'llm.summary',
                    'severity' => 'info',
                ]);
            }

            return [
                'findings' => $findings,
                'recommendations' => $parsed['recommendations'] ?? [],
                'confidence' => min(1.0, max(0.0, (float) ($parsed['confidence'] ?? 0.7))),
                'model' => $result->model,
            ];
        } catch (\Throwable $e) {
            Log::warning('Owner analytics LLM synthesis failed', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'findings' => $ruleFindings,
                'recommendations' => $this->defaultRecommendations($ruleFindings),
                'confidence' => 0.5,
            ];
        }
    }

    /**
     * @param  list<array<string, string>>  $findings
     * @return list<string>
     */
    private function defaultRecommendations(array $findings): array
    {
        $recs = [];
        foreach ($findings as $f) {
            $key = $f['evidence_key'] ?? '';
            if (str_contains($key, 'growth.roi') || str_contains($key, 'growth.conversionRate')) {
                $recs[] = 'Review ad targeting and landing message — ensure WhatsApp link and offer are clear.';
            }
            if (str_contains($key, 'inventory')) {
                $recs[] = 'Restock low-inventory bestsellers and promote alternatives.';
            }
            if (str_contains($key, 'revenue_change_pct')) {
                $recs[] = 'Compare week-over-week order volume and follow up on abandoned carts.';
            }
        }

        return array_values(array_unique($recs));
    }
}
