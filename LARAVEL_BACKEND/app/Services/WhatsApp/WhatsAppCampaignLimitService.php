<?php

namespace App\Services\WhatsApp;

use App\Models\Company;
use App\Models\WhatsAppCampaign;
use App\Services\PlanLimitService;
use Carbon\Carbon;

final class WhatsAppCampaignLimitService
{
    public static function getCampaignsLimit(Company $company): int
    {
        $plan = PlanLimitService::getCurrentPlanSlug($company);
        $limits = config('whatsapp.campaign.limits.'.$plan) ?? config('whatsapp.campaign.limits.starter');

        return (int) ($limits['campaigns_per_month'] ?? 2);
    }

    public static function getRecipientsLimit(Company $company): int
    {
        $plan = PlanLimitService::getCurrentPlanSlug($company);
        $limits = config('whatsapp.campaign.limits.'.$plan) ?? config('whatsapp.campaign.limits.starter');

        return (int) ($limits['recipients_per_campaign'] ?? 100);
    }

    public static function campaignsUsedThisMonth(Company $company): int
    {
        return WhatsAppCampaign::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [
                WhatsAppCampaign::STATUS_SENDING,
                WhatsAppCampaign::STATUS_COMPLETED,
                WhatsAppCampaign::STATUS_FAILED,
            ])
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();
    }

    public static function canCreateCampaign(Company $company): bool
    {
        return self::campaignsUsedThisMonth($company) < self::getCampaignsLimit($company);
    }

    /**
     * @return array{campaignsUsed: int, campaignsLimit: int, recipientsLimit: int}
     */
    public static function usageSummary(Company $company): array
    {
        return [
            'campaignsUsed' => self::campaignsUsedThisMonth($company),
            'campaignsLimit' => self::getCampaignsLimit($company),
            'recipientsLimit' => self::getRecipientsLimit($company),
        ];
    }
}
