<?php

namespace App\Services\PlatformPayments\Drivers;

use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\MpesaService;
use App\Services\PlatformPayments\Contracts\PlatformPaymentDriverInterface;

class PlatformMpesaDriver implements PlatformPaymentDriverInterface
{
    public function __construct(
        protected MpesaService $mpesaService,
    ) {}

    public function getId(): string
    {
        return 'mpesa';
    }

    public function getDisplayName(): string
    {
        return 'Lipa Na M-Pesa Express';
    }

    public function getSortOrder(): int
    {
        return 30;
    }

    public function isAvailable(): bool
    {
        if (! PaymentGateway::isEnabled('mpesa')) {
            return false;
        }

        $cfg = PaymentGateway::getConfig('mpesa');

        return ! empty($cfg['shortcode']) && ! empty($cfg['passkey']);
    }

    public function initiatePlanPayment(Company $company, Plan $plan, array $options = []): array
    {
        if (! $this->isAvailable()) {
            return ['success' => false, 'error' => 'M-Pesa platform payment is not available.'];
        }

        $phone = $options['phone'] ?? $company->phone ?? '';
        if (! $phone) {
            return ['success' => false, 'error' => 'M-Pesa phone number is required for STK push.'];
        }

        $amount = (float) $plan->price_amount;
        if ($amount <= 0 || $plan->is_free) {
            return ['success' => false, 'error' => 'Selected plan does not require payment.'];
        }

        $cfg = PaymentGateway::getConfig('mpesa');
        $callbackUrl = $cfg['callback_url'] ?? rtrim(config('app.url'), '/').'/api/mpesa/callback';

        $accountRef = 'SUB-'.$plan->slug.'-'.$company->id;
        $result = $this->mpesaService->stkPush(
            $phone,
            $amount,
            $accountRef,
            'Platform Subscription: '.$plan->name,
            $callbackUrl
        );

        if (isset($result['error'])) {
            return ['success' => false, 'error' => $result['error']];
        }

        $checkoutRequestId = $result['CheckoutRequestID'] ?? null;
        if (! $checkoutRequestId) {
            return ['success' => false, 'error' => $result['ResponseDescription'] ?? 'M-Pesa push failed'];
        }

        return [
            'success' => true,
            'gateway' => 'mpesa',
            'checkout_request_id' => $checkoutRequestId,
            'message' => 'STK push sent to '.$phone.'. Please enter your M-Pesa PIN on your phone to complete your subscription.',
            'type' => 'stk_push',
        ];
    }

    public function getMetadata(): array
    {
        $cfg = PaymentGateway::getConfig('mpesa');

        return [
            'currency' => strtoupper((string) ($cfg['currency'] ?? 'KES')),
            'env' => $cfg['env'] ?? 'sandbox',
            'supports_stk_push' => true,
            'supports_subscription_auto_renew' => false,
        ];
    }
}
