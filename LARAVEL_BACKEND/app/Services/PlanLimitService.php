<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Message;
use App\Models\Subscription;
use App\Services\Platform\EntitlementService;
use Carbon\Carbon;

/**
 * Centralized plan limits for messages, team, AI model access, and BYOK.
 * Delegates entitlements to EntitlementService (DB-backed plans.entitlements).
 */
final class PlanLimitService
{
    private static function entitlements(): EntitlementService
    {
        return app(EntitlementService::class);
    }

    public static function getMessageLimitForPlan(string $plan): int
    {
        return (int) (self::getLimitsForPlan($plan)['messages'] ?? 5000);
    }

    public static function getTeamLimitForPlan(string $plan): int
    {
        return (int) (self::getLimitsForPlan($plan)['team'] ?? 3);
    }

    public static function getAiCostLimitForPlan(string $plan): ?float
    {
        $value = self::getLimitsForPlan($plan)['ai_cost_usd'] ?? null;

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
        $period = self::entitlements()->currentBillingPeriod($company);

        return Message::whereHas('chat', fn ($q) => $q->where('company_id', $company->id))
            ->whereBetween('created_at', [$period['start'], $period['end']])
            ->count();
    }

    public static function getCurrentPlanSlug(Company $company): string
    {
        return self::entitlements()->currentPlanSlug($company);
    }

    public static function isWithinMessageLimit(Company $company): bool
    {
        $used = self::getMessagesUsedInCurrentPeriod($company);
        $limit = self::entitlements()->messageLimit($company);

        return $used < $limit;
    }

    public static function canAddTeamMember(Company $company): bool
    {
        return self::entitlements()->canAddTeamMember($company);
    }

    /** @return array<string, mixed> */
    public static function getLimitsForPlan(string $plan): array
    {
        return self::entitlements()->limitsForPlanSlug($plan);
    }
}
