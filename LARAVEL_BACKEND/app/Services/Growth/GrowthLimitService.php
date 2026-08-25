<?php

namespace App\Services\Growth;

use App\Models\Company;
use App\Models\SocialPost;
use App\Services\AI\AiGateway;
use App\Services\PlanLimitService;
use Carbon\Carbon;

final class GrowthLimitService
{
    public static function getAiPostsLimit(Company $company): int
    {
        $limits = PlanLimitService::getLimitsForPlan(PlanLimitService::getCurrentPlanSlug($company));
        if (array_key_exists('ai_posts_per_month', $limits)) {
            return (int) $limits['ai_posts_per_month'];
        }

        $plan = PlanLimitService::getCurrentPlanSlug($company);
        $config = config('growth.limits.'.$plan) ?? config('growth.limits.starter');

        return (int) ($config['ai_posts_per_month'] ?? 20);
    }

    public static function getAiImagesLimit(Company $company): int
    {
        $limits = PlanLimitService::getLimitsForPlan(PlanLimitService::getCurrentPlanSlug($company));
        if (array_key_exists('ai_images_per_month', $limits)) {
            return (int) $limits['ai_images_per_month'];
        }

        $plan = PlanLimitService::getCurrentPlanSlug($company);
        $config = config('growth.limits.'.$plan) ?? config('growth.limits.starter');

        return (int) ($config['ai_images_per_month'] ?? 10);
    }

    public static function aiImagesUsedThisMonth(Company $company): int
    {
        return \App\Models\AiRequestLog::query()
            ->where('company_id', $company->id)
            ->where('use_case', AiGateway::USE_CASE_IMAGE_GENERATION)
            ->where('success', true)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();
    }

    public static function canGenerateAiImage(Company $company): bool
    {
        return self::aiImagesUsedThisMonth($company) < self::getAiImagesLimit($company);
    }

    public static function getPlatformLimit(Company $company): int
    {
        $limits = PlanLimitService::getLimitsForPlan(PlanLimitService::getCurrentPlanSlug($company));
        if (array_key_exists('social_platforms', $limits)) {
            return (int) $limits['social_platforms'];
        }

        $plan = PlanLimitService::getCurrentPlanSlug($company);
        $config = config('growth.limits.'.$plan) ?? config('growth.limits.starter');

        return (int) ($config['platforms'] ?? 1);
    }

    public static function aiPostsUsedThisMonth(Company $company): int
    {
        return SocialPost::where('company_id', $company->id)
            ->where('ai_generated', true)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();
    }

    public static function canGenerateAiContent(Company $company): bool
    {
        return self::aiPostsUsedThisMonth($company) < self::getAiPostsLimit($company);
    }

    public static function connectedPlatformsCount(Company $company): int
    {
        return $company->socialAccounts()->where('status', 'connected')->count();
    }

    public static function canConnectPlatform(Company $company, string $platform): bool
    {
        if (! self::isGrowthEnabled($company)) {
            return false;
        }

        $existing = $company->socialAccounts()
            ->where('platform', $platform)
            ->where('status', 'connected')
            ->exists();

        if ($existing) {
            return true;
        }

        return self::connectedPlatformsCount($company) < self::getPlatformLimit($company);
    }

    public static function isGrowthEnabled(Company $company): bool
    {
        if ($company->growth_pilot_at) {
            return true;
        }

        $limits = PlanLimitService::getLimitsForPlan(PlanLimitService::getCurrentPlanSlug($company));
        if (array_key_exists('growth_enabled', $limits)) {
            return (bool) $limits['growth_enabled'];
        }

        $plan = PlanLimitService::getCurrentPlanSlug($company);
        $config = config('growth.limits.'.$plan) ?? config('growth.limits.starter');

        return (bool) ($config['growth_enabled'] ?? true);
    }

    /**
     * @return array{aiPostsUsed: int, aiPostsLimit: int, aiImagesUsed: int, aiImagesLimit: int, platformsConnected: int, platformLimit: int, growthEnabled: bool}
     */
    public static function usageSummary(Company $company): array
    {
        return [
            'aiPostsUsed' => self::aiPostsUsedThisMonth($company),
            'aiPostsLimit' => self::getAiPostsLimit($company),
            'aiImagesUsed' => self::aiImagesUsedThisMonth($company),
            'aiImagesLimit' => self::getAiImagesLimit($company),
            'platformsConnected' => self::connectedPlatformsCount($company),
            'platformLimit' => self::getPlatformLimit($company),
            'growthEnabled' => self::isGrowthEnabled($company),
        ];
    }

    /**
     * @return array<int, array{resource: string, level: string, message: string, percentUsed: float, projectedOverage?: bool}>
     */
    public static function usageWarnings(Company $company): array
    {
        $warnings = [];
        $summary = self::usageSummary($company);

        if (! $summary['growthEnabled']) {
            return $warnings;
        }

        $dayOfMonth = max(1, (int) now()->format('j'));
        $daysInMonth = (int) now()->daysInMonth;

        foreach (
            [
                ['resource' => 'AI posts', 'used' => $summary['aiPostsUsed'], 'limit' => $summary['aiPostsLimit'], 'key' => 'ai_posts'],
                ['resource' => 'AI images', 'used' => $summary['aiImagesUsed'], 'limit' => $summary['aiImagesLimit'], 'key' => 'ai_images'],
                ['resource' => 'Social platforms', 'used' => $summary['platformsConnected'], 'limit' => $summary['platformLimit'], 'key' => 'platforms'],
            ] as $row
        ) {
            if ($row['limit'] <= 0) {
                continue;
            }
            $percent = ($row['used'] / $row['limit']) * 100;
            $projected = in_array($row['key'], ['ai_posts', 'ai_images'], true)
                ? ($row['used'] / $dayOfMonth) * $daysInMonth
                : null;

            if ($row['used'] >= $row['limit']) {
                $warnings[] = [
                    'resource' => $row['resource'],
                    'level' => 'critical',
                    'message' => "{$row['resource']} limit reached ({$row['used']}/{$row['limit']}). Upgrade to continue.",
                    'percentUsed' => round($percent, 1),
                    'projectedOverage' => true,
                ];
            } elseif ($percent >= 80) {
                $warnings[] = [
                    'resource' => $row['resource'],
                    'level' => 'warning',
                    'message' => "{$row['resource']} at {$row['used']}/{$row['limit']} — approaching limit.",
                    'percentUsed' => round($percent, 1),
                    'projectedOverage' => $projected !== null && $projected > $row['limit'],
                ];
            } elseif ($projected !== null && $projected > $row['limit']) {
                $warnings[] = [
                    'resource' => $row['resource'],
                    'level' => 'info',
                    'message' => "At current pace you may exceed {$row['resource']} this month (~".(int) ceil($projected)." projected).",
                    'percentUsed' => round($percent, 1),
                    'projectedOverage' => true,
                ];
            }
        }

        return $warnings;
    }

    public static function notifyIfLimitReached(Company $company, string $resource): void
    {
        $summary = self::usageSummary($company);
        $map = [
            'ai_posts' => ['used' => $summary['aiPostsUsed'], 'limit' => $summary['aiPostsLimit'], 'label' => 'AI posts'],
            'ai_images' => ['used' => $summary['aiImagesUsed'], 'limit' => $summary['aiImagesLimit'], 'label' => 'AI images'],
            'platforms' => ['used' => $summary['platformsConnected'], 'limit' => $summary['platformLimit'], 'label' => 'social platforms'],
        ];

        if (! isset($map[$resource])) {
            return;
        }

        $row = $map[$resource];
        if ($row['used'] < $row['limit']) {
            return;
        }

        $cacheKey = "growth_limit_notified:{$company->id}:{$resource}:".now()->format('Y-m');
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return;
        }

        app(\App\Services\CompanyInAppNotificationService::class)->recordGrowthLimitWarning(
            $company,
            $row['label'],
            $row['used'],
            $row['limit']
        );
        \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->endOfMonth());
    }
}
