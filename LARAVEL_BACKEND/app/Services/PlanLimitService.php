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
        $limit = self::getLimitsForPlan($plan)['messages'] ?? 5000;

        // null = unlimited; callers that need a numeric UI value use a large sentinel.
        return $limit === null ? PHP_INT_MAX : (int) $limit;
    }

    public static function hasUnlimitedMessages(Company $company): bool
    {
        return self::entitlements()->hasUnlimitedMessages($company);
    }

    public static function getTeamLimitForPlan(string $plan): int
    {
        return (int) (self::getLimitsForPlan($plan)['team'] ?? 3);
    }

    public static function getWhatsAppNumberLimitForPlan(string $plan): int
    {
        return max(1, (int) (self::getLimitsForPlan($plan)['whatsapp_numbers'] ?? 1));
    }

    public static function planHasApiAccess(string $plan): bool
    {
        return (bool) (self::getLimitsForPlan($plan)['api_access'] ?? false);
    }

    public static function planHasAnalytics(string $plan): bool
    {
        return (bool) (self::getLimitsForPlan($plan)['analytics'] ?? false);
    }

    public static function companyHasApiAccess(Company $company): bool
    {
        return self::entitlements()->hasApiAccess($company);
    }

    public static function companyHasAnalytics(Company $company): bool
    {
        return self::entitlements()->hasAnalyticsAccess($company);
    }

    public static function companyAllowsProductType(Company $company, string $type): bool
    {
        return self::entitlements()->allowsProductType($company, $type);
    }

    public static function companyAllowsBookings(Company $company): bool
    {
        return self::entitlements()->allowsBookings($company);
    }

    public static function getMaxBookingsPerMonth(Company $company): ?int
    {
        return self::entitlements()->maxBookingsPerMonth($company);
    }

    public static function canConnectWhatsApp(Company $company, ?string $phoneNumberId = null): bool
    {
        return self::entitlements()->canConnectWhatsAppNumber($company, $phoneNumberId);
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
        if (self::entitlements()->hasUnlimitedMessages($company)) {
            return true;
        }

        $used = self::getMessagesUsedInCurrentPeriod($company);
        $limit = self::entitlements()->messageLimit($company) ?? 5000;

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
