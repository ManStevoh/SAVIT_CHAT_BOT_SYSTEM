<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Message;
use App\Models\Subscription;
use Carbon\Carbon;

/**
 * Centralized plan limits for messages and team. Used by subscription usage
 * display and by ProcessIncomingWhatsAppMessage to enforce message caps.
 */
final class PlanLimitService
{
    /** Plan slug => [messages => int, team => int] */
    private const DEFAULTS = [
        'starter' => ['messages' => 5000, 'team' => 3],
        'professional' => ['messages' => 50000, 'team' => 10],
        'enterprise' => ['messages' => 500000, 'team' => 50],
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

    /**
     * Messages sent (any sender) for the company in the current subscription period.
     */
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

    /**
     * Current plan slug for the company (from latest subscription).
     */
    public static function getCurrentPlanSlug(Company $company): string
    {
        $subscription = Subscription::where('company_id', $company->id)
            ->orderByDesc('end_date')
            ->first();

        return $subscription?->plan ?? 'starter';
    }

    /**
     * Whether the company is within its message limit for the current period.
     * Returns false if at or over limit (so we should not send another bot reply).
     */
    public static function isWithinMessageLimit(Company $company): bool
    {
        $used = self::getMessagesUsedInCurrentPeriod($company);
        $plan = self::getCurrentPlanSlug($company);
        $limit = self::getMessageLimitForPlan($plan);

        return $used < $limit;
    }

    /** All limits for a plan (for usage API). */
    public static function getLimitsForPlan(string $plan): array
    {
        return self::DEFAULTS[$plan] ?? self::DEFAULTS['starter'];
    }
}
