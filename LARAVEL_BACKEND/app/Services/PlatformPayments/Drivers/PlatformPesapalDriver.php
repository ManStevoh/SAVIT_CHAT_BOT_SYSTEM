<?php

namespace App\Services\PlatformPayments\Drivers;

use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\PesapalService;
use App\Services\PlatformPayments\Contracts\PlatformPaymentDriverInterface;

class PlatformPesapalDriver implements PlatformPaymentDriverInterface
{
    public function __construct(
        protected PesapalService $pesapalService,
    ) {}

    public function getId(): string
    {
        return 'pesapal';
    }

    public function getDisplayName(): string
    {
        return 'Pesapal (Cards, M-Pesa & Mobile Money)';
    }

    public function getSortOrder(): int
    {
        return 25;
    }

    public function isAvailable(): bool
    {
        if (! PaymentGateway::isEnabled('pesapal')) {
            return false;
        }

        $cfg = PaymentGateway::getConfig('pesapal');

        return ! empty($cfg['consumer_key']) && ! empty($cfg['consumer_secret']);
    }

    public function initiatePlanPayment(Company $company, Plan $plan, array $options = []): array
    {
        if (! $this->isAvailable()) {
            return ['success' => false, 'error' => 'Pesapal platform payment is not available.'];
        }

        $amount = (float) $plan->price_amount;
        if ($amount <= 0 || $plan->is_free) {
            return ['success' => false, 'error' => 'Selected plan does not require payment.'];
        }

        $reference = 'sub_pesapal_'.uniqid().'_'.$company->id;
        $callbackUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/').'/dashboard/subscription/complete';

        $cfg = PaymentGateway::getConfig('pesapal');
        $currency = strtoupper((string) ($cfg['currency'] ?? 'KES'));

        $nameParts = explode(' ', trim((string) ($company->name ?? 'Company')), 2);

        $result = $this->pesapalService->submitOrderRequest([
            'id' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'description' => 'Subscription plan: '.$plan->name,
            'callback_url' => $callbackUrl,
            'billing_address' => [
                'email_address' => $company->email,
                'phone_number' => $options['phone'] ?? $company->phone ?? '',
                'first_name' => $nameParts[0] ?: 'Company',
                'last_name' => $nameParts[1] ?? 'Admin',
                'country_code' => 'KE',
            ],
        ]);

        if (! ($result['success'] ?? false) || empty($result['redirect_url'])) {
            return ['success' => false, 'error' => $result['error'] ?? 'Could not initialize Pesapal payment.'];
        }

        return [
            'success' => true,
            'gateway' => 'pesapal',
            'checkout_url' => $result['redirect_url'],
            'reference' => $result['merchant_reference'] ?? $reference,
            'order_tracking_id' => $result['order_tracking_id'] ?? null,
            'type' => 'redirect',
        ];
    }

    public function getMetadata(): array
    {
        $cfg = PaymentGateway::getConfig('pesapal');

        return [
            'currency' => strtoupper((string) ($cfg['currency'] ?? 'KES')),
            'env' => $cfg['env'] ?? 'sandbox',
            'supports_subscription_auto_renew' => false,
        ];
    }
}
