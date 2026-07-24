<?php

namespace App\Services\Agent;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Services\Platform\EntitlementService;

/**
 * Keep company agent_commerce_enabled aligned with plan entitlements.
 * All plans entitle the conversational AI OS by default; sync enables it on upgrade/trial.
 */
final class AgentCommerceProvisioningService
{
    public function __construct(
        protected EntitlementService $entitlements,
    ) {}

    /**
     * Enable agent commerce when the plan entitles it (all paid plans by default).
     * Does not force-disable an existing explicit on/off choice after first sync —
     * but enables when entitled and currently off (upgrade / new trial).
     */
    public function syncForCompany(Company $company): CompanySetting
    {
        $limits = $this->entitlements->limitsForCompany($company);
        $entitled = (bool) ($limits['agent_commerce'] ?? false);

        $settings = CompanySetting::firstOrCreate(
            ['company_id' => $company->id],
            ['agent_commerce_enabled' => $entitled]
        );

        if ($entitled && ! $settings->agent_commerce_enabled) {
            $settings->agent_commerce_enabled = true;
            $settings->save();
        }

        return $settings->fresh();
    }

    public function isEntitled(Company $company): bool
    {
        $limits = $this->entitlements->limitsForCompany($company);

        return (bool) ($limits['agent_commerce'] ?? false);
    }
}
