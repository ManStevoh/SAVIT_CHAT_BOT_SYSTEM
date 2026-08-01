<?php

namespace App\Services\PlatformPayments\Drivers;

use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\PlatformPayments\Contracts\PlatformPaymentDriverInterface;
use App\Services\StripeService;

class PlatformStripeDriver implements PlatformPaymentDriverInterface
{
    public function __construct(
        protected StripeService $stripeService,
    ) {}

    public function getId(): string
    {
        return 'stripe';
    }

    public function getDisplayName(): string
    {
        return 'Stripe (Cards & Apple Pay)';
    }

    public function getSortOrder(): int
    {
        return 10;
    }

    public function isAvailable(): bool
    {
        if (! PaymentGateway::isEnabled('stripe')) {
            return false;
        }

        $cfg = PaymentGateway::getConfig('stripe');

        return ! empty($cfg['secret']);
    }

    public function initiatePlanPayment(Company $company, Plan $plan, array $options = []): array
    {
        if (! $this->isAvailable()) {
            return ['success' => false, 'error' => 'Stripe platform payment is not available.'];
        }

        $url = $this->stripeService->createCheckoutSession($company, $plan);
        if (! $url) {
            return ['success' => false, 'error' => 'Could not create Stripe checkout session.'];
        }

        return [
            'success' => true,
            'gateway' => 'stripe',
            'checkout_url' => $url,
            'type' => 'redirect',
        ];
    }

    public function getMetadata(): array
    {
        $cfg = PaymentGateway::getConfig('stripe');

        return [
            'currency' => strtoupper((string) ($cfg['currency'] ?? 'KES')),
            'env' => $cfg['env'] ?? 'sandbox',
            'supports_subscription_auto_renew' => true,
        ];
    }
}
