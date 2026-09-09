<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\PlatformSetting;

class BillingResolutionService
{
    /**
     * Resolve the effective billing configuration for a company by evaluating:
     * 1. Company tenant-level override (highest priority)
     * 2. PlatformSetting global defaults (base platform fallback)
     *
     * @return array{
     *     model: string,
     *     is_commission_active: bool,
     *     rate: float,
     *     basis: string,
     *     waive_subscription: bool,
     *     threshold: float,
     *     grace_period_days: int
     * }
     */
    public function resolve(Company $company): array
    {
        $platform = PlatformSetting::first();

        // 1. Resolve Billing Model
        $model = $company->billing_model;
        if (empty($model)) {
            $model = $platform?->default_billing_model ?? 'subscription';
        }

        // 2. Resolve Commission Rate (%)
        $rate = $company->commission_rate !== null
            ? (float) $company->commission_rate
            : (float) ($platform?->default_commission_rate ?? 5.00);

        // 3. Resolve Commission Basis
        $basis = $company->commission_basis ?: 'total';

        // 4. Resolve Waive Subscription Fee
        $waiveSubscription = (bool) ($company->waive_subscription_fee ?? true);

        // 5. Resolve Invoicing Threshold for manual payments
        $threshold = $company->commission_invoice_threshold !== null
            ? (float) $company->commission_invoice_threshold
            : (float) ($platform?->default_commission_threshold ?? 1000.00);

        // 6. Grace Period Days
        $graceDays = (int) ($platform?->commission_grace_period_days ?? 7);

        $isCommissionActive = in_array(strtolower($model), ['commission', 'hybrid'], true);

        return [
            'model'                => strtolower($model),
            'is_commission_active' => $isCommissionActive,
            'rate'                 => max(0.0, round($rate, 2)),
            'basis'                => $basis,
            'waive_subscription'   => $waiveSubscription,
            'threshold'            => max(0.0, round($threshold, 2)),
            'grace_period_days'    => max(1, $graceDays),
        ];
    }
}
