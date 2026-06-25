<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Message;
use App\Models\Subscription;
use Carbon\Carbon;

/**
 * Centralized plan limits for messages, team, AI model access, and BYOK.
 */
final class PlanLimitService
{
    /**
     * Plan slug => limits.
     *
     * ai_model_modes: auto | platform_default | specific
     * allow_byok: whether company may use own API keys
     * credential_modes: platform | company_preferred | company (subset when BYOK allowed)
     */
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

    public static function getMessageLimitForPlan(string $plan): int
    {
        $limits = self::DEFAULTS[$plan] ?? self::DEFAULTS['starter'];

        return (int) $limits['messages'];
    }

    public static function getTeamLimitForPlan(string $plan): int
    {
        $limits = self::DEFAULTS[$plan] ?? self::DEFAULTS['starter'];

        return (int) $limits['team'];
    }

    public static function getAiCostLimitForPlan(string $plan): ?float
    {
        $limits = self::DEFAULTS[$plan] ?? self::DEFAULTS['starter'];
        $value = $limits['ai_cost_usd'] ?? null;

        return $value === null ? null : (float) $value;
    }

    /**
     * @return array<int, string>
     */
    public static function getAllowedAiModelModes(string $plan): array
    {
        $limits = self::getLimitsForPlan($plan);

        return $limits['ai_model_modes'] ?? ['auto'];
    }

    public static function planAllowsByok(string $plan): bool
    {
        return (bool) (self::getLimitsForPlan($plan)['allow_byok'] ?? false);
    }

    /**
     * @return array<int, string>
     */
    public static function getAllowedCredentialModes(string $plan): array
    {
        if (! self::planAllowsByok($plan)) {
            return ['platform'];
        }

        return self::getLimitsForPlan($plan)['credential_modes'] ?? ['platform', 'company_preferred', 'company'];
    }

    public static function isAiModelModeAllowed(string $plan, string $mode): bool
    {
        return in_array($mode, self::getAllowedAiModelModes($plan), true);
    }

    public static function isCredentialModeAllowed(string $plan, string $mode): bool
    {
        return in_array($mode, self::getAllowedCredentialModes($plan), true);
    }

    /**
     * Effective model mode after plan policy (may differ from stored setting on downgraded plans).
     */
    public static function effectiveAiModelMode(Company $company): string
    {
        $company->loadMissing('settings');
        $requested = $company->settings?->ai_model_mode ?? 'auto';
        $plan = self::getCurrentPlanSlug($company);
        $allowed = self::getAllowedAiModelModes($plan);

        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        return $allowed[0] ?? 'auto';
    }

    /**
     * Effective credential mode after plan policy.
     */
    public static function effectiveCredentialMode(Company $company): string
    {
        $company->loadMissing('settings');
        $requested = $company->settings?->ai_credential_mode ?? 'platform';
        $plan = self::getCurrentPlanSlug($company);
        $allowed = self::getAllowedCredentialModes($plan);

        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        return 'platform';
    }

    /**
     * @return array<string, mixed>
     */
    public static function aiPlanCapabilities(string $plan): array
    {
        return [
            'plan' => $plan,
            'allowedModelModes' => self::getAllowedAiModelModes($plan),
            'allowByok' => self::planAllowsByok($plan),
            'allowedCredentialModes' => self::getAllowedCredentialModes($plan),
            'aiCostLimitUsd' => self::getAiCostLimitForPlan($plan),
        ];
    }

    public static function getMessagesUsedInCurrentPeriod(Company $company): int
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

        return Message::whereHas('chat', fn ($q) => $q->where('company_id', $company->id))
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    public static function getCurrentPlanSlug(Company $company): string
    {
        $subscription = Subscription::where('company_id', $company->id)
            ->orderByDesc('end_date')
            ->first();

        return $subscription?->plan ?? 'starter';
    }

    public static function isWithinMessageLimit(Company $company): bool
    {
        $used = self::getMessagesUsedInCurrentPeriod($company);
        $plan = self::getCurrentPlanSlug($company);
        $limit = self::getMessageLimitForPlan($plan);

        return $used < $limit;
    }

    /** @return array<string, mixed> */
    public static function getLimitsForPlan(string $plan): array
    {
        return self::DEFAULTS[$plan] ?? self::DEFAULTS['starter'];
    }
}
