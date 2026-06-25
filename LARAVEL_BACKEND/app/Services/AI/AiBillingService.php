<?php

namespace App\Services\AI;

use App\Models\AiRequestLog;
use App\Models\Company;
use App\Services\PlanLimitService;
use Carbon\Carbon;

/**
 * Platform AI billing: markup on provider cost and monthly spend limits per plan.
 * BYOK (company credential) usage is logged but not billed to the platform quota.
 */
final class AiBillingService
{
    public function __construct(private AiLearningConfig $learningConfig) {}

    public function billedCostUsd(float $estimatedCostUsd, string $credentialSource): float
    {
        if ($credentialSource === 'company') {
            return 0.0;
        }

        $markup = max(0.0, (float) ($this->learningConfig->all()['aiCostMarkupPercent'] ?? 0));

        return round($estimatedCostUsd * (1 + ($markup / 100)), 6);
    }

    /**
     * Whether the company may incur another platform-billed AI request this period.
     */
    public function isWithinPlatformAiBudget(Company $company): bool
    {
        $limit = PlanLimitService::getAiCostLimitForPlan(PlanLimitService::getCurrentPlanSlug($company));
        if ($limit === null || $limit <= 0) {
            return true;
        }

        $spent = $this->platformBilledCostInCurrentPeriod($company);

        return $spent < $limit;
    }

    public function platformBilledCostInCurrentPeriod(Company $company): float
    {
        [$start, $end] = $this->billingPeriodBounds($company);

        return (float) AiRequestLog::query()
            ->where('company_id', $company->id)
            ->where('credential_source', 'platform')
            ->whereBetween('created_at', [$start, $end])
            ->sum('billed_cost_usd');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function billingPeriodBounds(Company $company): array
    {
        $subscription = $company->subscriptions()->orderByDesc('end_date')->first();
        if ($subscription) {
            return [
                Carbon::parse($subscription->start_date)->startOfDay(),
                Carbon::parse($subscription->end_date)->endOfDay(),
            ];
        }

        return [now()->startOfMonth(), now()->endOfMonth()];
    }

    /**
     * @return array<string, mixed>
     */
    public function usageSummary(Company $company): array
    {
        [$start, $end] = $this->billingPeriodBounds($company);
        $plan = PlanLimitService::getCurrentPlanSlug($company);
        $limit = PlanLimitService::getAiCostLimitForPlan($plan);

        $query = AiRequestLog::query()
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$start, $end]);

        $platformCost = (float) (clone $query)->where('credential_source', 'platform')->sum('billed_cost_usd');
        $companyKeyCost = (float) (clone $query)->where('credential_source', 'company')->sum('estimated_cost_usd');
        $totalTokens = (int) (clone $query)->sum('total_tokens');
        $requests = (int) (clone $query)->count();

        return [
            'periodStart' => $start->toIso8601String(),
            'periodEnd' => $end->toIso8601String(),
            'totalRequests' => $requests,
            'totalTokens' => $totalTokens,
            'platformBilledCostUsd' => round($platformCost, 4),
            'companyKeyEstimatedCostUsd' => round($companyKeyCost, 4),
            'platformCostLimitUsd' => $limit,
            'credentialMode' => app(CompanyAiCredentialService::class)->credentialMode($company),
        ];
    }
}
