<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;
use App\Models\PaymentGateway;
use App\Services\PaymentGateways\PaymentGatewayRegistry;

$company = Company::find(3);
if (!$company) {
    echo "Company 3 not found!\n";
    exit;
}

$settings = $company->settings;
echo "Company settings orders_collect_payment_enabled: " . ($settings->orders_collect_payment_enabled ? 'true' : 'false') . "\n";
echo "Company settings orders_accept_paystack: " . ($settings->orders_accept_paystack ? 'true' : 'false') . "\n";

echo "All PaymentGateways in DB:\n";
foreach (PaymentGateway::all() as $gw) {
    echo "- slug: " . $gw->slug . ", is_enabled: " . ($gw->is_enabled ? 'true' : 'false') . "\n";
}

$registry = app(PaymentGatewayRegistry::class);
$drivers = $registry->getAvailableDrivers($company);
echo "Available Drivers for Company 3:\n";
foreach ($drivers as $d) {
    echo "- " . $d->getId() . " (" . $d->getDisplayName() . ")\n";
}
