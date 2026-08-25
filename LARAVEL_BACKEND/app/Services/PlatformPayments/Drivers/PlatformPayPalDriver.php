<?php

namespace App\Services\PlatformPayments\Drivers;

use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\PayPalService;
use App\Services\PlatformPayments\Contracts\PlatformPaymentDriverInterface;
use App\Services\RegionalPricingService;

class PlatformPayPalDriver implements PlatformPaymentDriverInterface
{
    public function __construct(
        protected PayPalService $paypalService,
    ) {}

    public function getId(): string
    {
        return 'paypal';
    }

    public function getDisplayName(): string
    {
        return 'PayPal (Cards & PayPal Balance)';
    }

    public function getSortOrder(): int
    {
        return 35;
    }

    public function isAvailable(): bool
    {
        if (! PaymentGateway::isEnabled('paypal')) {
            return false;
        }

        $cfg = PaymentGateway::getConfig('paypal');

        return ! empty($cfg['client_id']) && ! empty($cfg['client_secret']);
    }

    public function initiatePlanPayment(Company $company, Plan $plan, array $options = []): array
    {
        if (! $this->isAvailable()) {
            return ['success' => false, 'error' => 'PayPal platform payment is not available.'];
        }

        $cfg = PaymentGateway::getConfig('paypal');
        $currency = strtoupper((string) ($cfg['currency'] ?? 'USD'));
        $amount = (float) (app(RegionalPricingService::class)->amountForPlan($plan, $currency) ?? $plan->price_amount ?? 0);
        if ($amount <= 0 || $plan->is_free) {
            return ['success' => false, 'error' => 'Selected plan does not require payment.'];
        }

        $url = $this->paypalService->createCheckoutSession($company, $plan);
        if (! $url) {
            return ['success' => false, 'error' => 'Could not create PayPal checkout session.'];
        }

        return [
            'success' => true,
            'gateway' => 'paypal',
            'checkout_url' => $url,
            'type' => 'redirect',
        ];
    }

    public function getMetadata(): array
    {
        $cfg = PaymentGateway::getConfig('paypal');

        return [
            'currency' => strtoupper((string) ($cfg['currency'] ?? 'USD')),
            'env' => $cfg['env'] ?? 'sandbox',
            'supports_subscription_auto_renew' => false,
        ];
    }
}
