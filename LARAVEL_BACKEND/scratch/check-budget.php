<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;
use App\Services\AI\AiBillingService;
use App\Services\Platform\EntitlementService;

$company = Company::find(3);
if (!$company) {
    echo "Company 3 not found!\n";
    exit;
}

$billing = app(AiBillingService::class);
$entitlement = app(EntitlementService::class);

echo "Company Name: " . $company->name . "\n";
echo "Current Plan Slug: " . \App\Services\PlanLimitService::getCurrentPlanSlug($company) . "\n";

$limits = $entitlement->limitsForCompany($company);
echo "Limits from Company:\n";
print_r($limits);

$bounds = $billing->billingPeriodBounds($company);
echo "Billing Period Bounds: " . $bounds[0]->toIso8601String() . " to " . $bounds[1]->toIso8601String() . "\n";

$spent = $billing->platformBilledCostInCurrentPeriod($company);
echo "Platform Billed Cost in Current Period: $" . number_format($spent, 6) . "\n";

$limit = $limits['ai_cost_usd'] ?? null;
echo "AI Cost Limit: " . ($limit === null ? "Unlimited" : "$".$limit) . "\n";

echo "Is within platform AI budget: " . ($billing->isWithinPlatformAiBudget($company) ? "YES" : "NO") . "\n";
