<?php

namespace App\Services\Agent;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Services\Platform\EntitlementService;

/**
 * Keep company agent flags aligned with plan entitlements.
 * Plans without agent commerce (e.g. Starter) get AI fully switched off so the
 * inbox stays manual; entitled plans are enabled on upgrade/trial.
 */
final class AgentCommerceProvisioningService
{
    public function __construct(
        protected EntitlementService $entitlements,
    ) {}

    /**
     * Enable agent commerce + auto-reply when the plan entitles it.
     * Force-disable both when the plan does not (downgrade / Starter) so no AI
     * path can fire for companies outside their allowance.
     */
    public function syncForCompany(Company $company): CompanySetting
    {
        $limits = $this->entitlements->limitsForCompany($company);
        $entitled = (bool) ($limits['agent_commerce'] ?? false);

        $settings = CompanySetting::firstOrCreate(
            ['company_id' => $company->id],
            [
                'agent_commerce_enabled' => $entitled,
                'auto_reply_enabled' => $entitled,
            ]
        );

        if ($entitled) {
            if (! $settings->agent_commerce_enabled) {
                $settings->agent_commerce_enabled = true;
                $settings->save();
            }

            return $settings->fresh();
        }

        $changed = false;
        if ($settings->agent_commerce_enabled) {
            $settings->agent_commerce_enabled = false;
            $changed = true;
        }
        if ($settings->auto_reply_enabled) {
            $settings->auto_reply_enabled = false;
            $changed = true;
        }
        if ($changed) {
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
