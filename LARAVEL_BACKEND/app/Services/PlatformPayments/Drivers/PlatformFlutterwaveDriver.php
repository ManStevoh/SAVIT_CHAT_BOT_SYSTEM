<?php

namespace App\Services\PlatformPayments\Drivers;

use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\FlutterwaveService;
use App\Services\PlatformPayments\Contracts\PlatformPaymentDriverInterface;
use App\Services\RegionalPricingService;

class PlatformFlutterwaveDriver implements PlatformPaymentDriverInterface
{
    public function __construct(
        protected FlutterwaveService $flutterwaveService,
    ) {}

    public function getId(): string
    {
        return 'flutterwave';
    }

    public function getDisplayName(): string
    {
        return 'Flutterwave (Cards, Mobile Money, Bank & USSD)';
    }

    public function getSortOrder(): int
    {
        return 30;
    }

    public function isAvailable(): bool
    {
        if (! PaymentGateway::isEnabled('flutterwave')) {
            return false;
        }

        $cfg = PaymentGateway::getConfig('flutterwave');

        return ! empty($cfg['secret_key']);
    }

    public function initiatePlanPayment(Company $company, Plan $plan, array $options = []): array
    {
        if (! $this->isAvailable()) {
            return ['success' => false, 'error' => 'Flutterwave platform payment is not available.'];
        }

        $cfg = PaymentGateway::getConfig('flutterwave');
        $currency = strtoupper((string) ($cfg['currency'] ?? 'KES'));
        $amount = (float) (app(RegionalPricingService::class)->amountForPlan($plan, $currency) ?? $plan->price_amount ?? 0);
        if ($amount <= 0 || $plan->is_free) {
            return ['success' => false, 'error' => 'Selected plan does not require payment.'];
        }

        $reference = 'sub_flw_'.uniqid().'_'.$company->id;
        $callbackUrl = url('/api/flutterwave/callback');

        $nameParts = explode(' ', trim((string) ($company->name ?? 'Company')), 2);

        $result = $this->flutterwaveService->initializePayment([
            'tx_ref' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'title' => 'Subscription plan: '.$plan->name,
            'description' => 'Platform subscription payment for '.$plan->name,
            'redirect_url' => $callbackUrl,
            'customer' => [
                'email' => $company->email,
                'phonenumber' => $options['phone'] ?? $company->phone ?? '',
                'name' => $company->name ?? 'Company Admin',
            ],
        ]);

        if (! ($result['success'] ?? false) || empty($result['link'])) {
            return ['success' => false, 'error' => $result['error'] ?? 'Could not initialize Flutterwave payment.'];
        }

        return [
            'success' => true,
            'gateway' => 'flutterwave',
            'checkout_url' => $result['link'],
            'reference' => $result['tx_ref'] ?? $reference,
            'type' => 'redirect',
        ];
    }

    public function getMetadata(): array
    {
        $cfg = PaymentGateway::getConfig('flutterwave');

        return [
            'currency' => strtoupper((string) ($cfg['currency'] ?? 'KES')),
            'env' => $cfg['env'] ?? 'sandbox',
            'supports_subscription_auto_renew' => false,
        ];
    }
}
