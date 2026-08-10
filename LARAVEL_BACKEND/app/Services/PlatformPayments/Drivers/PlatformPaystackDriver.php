<?php

namespace App\Services\PlatformPayments\Drivers;

use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\PaystackService;
use App\Services\PlatformPayments\Contracts\PlatformPaymentDriverInterface;
use App\Services\RegionalPricingService;

class PlatformPaystackDriver implements PlatformPaymentDriverInterface
{
    public function __construct(
        protected PaystackService $paystackService,
    ) {}

    public function getId(): string
    {
        return 'paystack';
    }

    public function getDisplayName(): string
    {
        return 'Paystack (Cards, Transfer & Mobile)';
    }

    public function getSortOrder(): int
    {
        return 20;
    }

    public function isAvailable(): bool
    {
        if (! PaymentGateway::isEnabled('paystack')) {
            return false;
        }

        $cfg = PaymentGateway::getConfig('paystack');

        return ! empty($cfg['secret_key']);
    }

    public function initiatePlanPayment(Company $company, Plan $plan, array $options = []): array
    {
        if (! $this->isAvailable()) {
            return ['success' => false, 'error' => 'Paystack platform payment is not available.'];
        }

        $cfg = PaymentGateway::getConfig('paystack');
        $currency = strtoupper((string) ($cfg['currency'] ?? 'KES'));
        $amount = (float) (app(RegionalPricingService::class)->amountForPlan($plan, $currency) ?? $plan->price_amount ?? 0);
        if ($amount <= 0 || $plan->is_free) {
            return ['success' => false, 'error' => 'Selected plan does not require payment.'];
        }

        $reference = 'sub_paystack_'.uniqid().'_'.$company->id;
        $callbackUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/').'/dashboard/subscription/complete';

        $result = $this->paystackService->initializeTransaction(
            $company->email,
            (int) round($amount * 100),
            $reference,
            $callbackUrl,
            [
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'plan_slug' => $plan->slug,
                'type' => 'subscription',
                'currency' => $currency,
            ]
        );

        if (! empty($result['error']) || empty($result['authorization_url'])) {
            return ['success' => false, 'error' => $result['error'] ?? 'Could not initialize Paystack payment.'];
        }

        return [
            'success' => true,
            'gateway' => 'paystack',
            'checkout_url' => $result['authorization_url'],
            'reference' => $result['reference'] ?? $reference,
            'type' => 'redirect',
        ];
    }

    public function getMetadata(): array
    {
        $cfg = PaymentGateway::getConfig('paystack');

        return [
            'currency' => strtoupper((string) ($cfg['currency'] ?? 'KES')),
            'env' => $cfg['env'] ?? 'sandbox',
            'supports_subscription_auto_renew' => true,
        ];
    }
}
