<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\CompanyEntitlementOverride;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;

/**
 * DB-backed plan entitlements — wraps hardcoded defaults when plans.entitlements is null.
 */
final class EntitlementService
{
    /** @var array<string, array<string, mixed>> */
    private const DEFAULTS = [
        'starter' => [
            'messages' => 5000,
            'team' => 3,
            'ai_cost_usd' => 5.0,
            'ai_model_modes' => ['auto'],
            'allow_byok' => false,
            'credential_modes' => ['platform'],
        ],
        'professional' => [
            'messages' => 50000,
            'team' => 10,
            'ai_cost_usd' => 50.0,
            'ai_model_modes' => ['auto', 'platform_default'],
            'allow_byok' => true,
            'credential_modes' => ['platform', 'company_preferred'],
        ],
        'enterprise' => [
            'messages' => 500000,
            'team' => 50,
            'ai_cost_usd' => null,
            'ai_model_modes' => ['auto', 'platform_default', 'specific'],
            'allow_byok' => true,
            'credential_modes' => ['platform', 'company_preferred', 'company'],
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function limitsForPlanSlug(string $planSlug): array
    {
        $defaults = self::DEFAULTS[$planSlug] ?? self::DEFAULTS['starter'];
        $plan = Plan::where('slug', $planSlug)->first();
        $fromDb = is_array($plan?->entitlements) ? $plan->entitlements : [];

        return array_merge($defaults, $fromDb);
    }

    /**
     * @return array<string, mixed>
     */
    public function limitsForCompany(Company $company): array
    {
        $slug = $this->currentPlanSlug($company);
        $limits = $this->limitsForPlanSlug($slug);
        $override = CompanyEntitlementOverride::where('company_id', $company->id)->first();
        if ($override && is_array($override->overrides)) {
            $limits = array_merge($limits, $override->overrides);
        }

        return $limits;
    }

    public function currentPlanSlug(Company $company): string
    {
        $subscription = Subscription::where('company_id', $company->id)
            ->orderByDesc('end_date')
            ->first();

        return $subscription?->plan ?? 'starter';
    }

    public function messageLimit(Company $company): int
    {
        return (int) ($this->limitsForCompany($company)['messages'] ?? 5000);
    }

    public function teamLimit(Company $company): int
    {
        return (int) ($this->limitsForCompany($company)['team'] ?? 3);
    }

    public function canAddTeamMember(Company $company): bool
    {
        $count = $company->users()->count();

        return $count < $this->teamLimit($company);
    }

    public function aiCostLimit(Company $company): ?float
    {
        $value = $this->limitsForCompany($company)['ai_cost_usd'] ?? null;

        return $value === null ? null : (float) $value;
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function currentBillingPeriod(Company $company): array
    {
        $subscription = Subscription::where('company_id', $company->id)
            ->orderByDesc('end_date')
            ->first();

        $start = $subscription
            ? Carbon::parse($subscription->start_date)->startOfDay()
            : now()->startOfMonth();
        $end = $subscription
            ? Carbon::parse($subscription->end_date)->endOfDay()
            : now()->endOfMonth();

        return ['start' => $start, 'end' => $end];
    }
}
