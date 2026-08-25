<?php

namespace App\Services\PaymentGateways\Drivers;

use App\Models\Company;
use App\Models\Order;
use App\Services\PayPalService;
use App\Services\PaymentGateways\Contracts\PaymentGatewayDriverInterface;

class PayPalGatewayDriver implements PaymentGatewayDriverInterface
{
    public function getId(): string
    {
        return 'paypal';
    }

    public function getDisplayName(): string
    {
        return 'PayPal (Cards & PayPal Balance)';
    }

    public function getCategory(): string
    {
        return 'card_online';
    }

    public function getSortOrder(): int
    {
        return 38;
    }

    public function isConfigured(Company $company): bool
    {
        return $company->settings?->hasOrderPaymentPayPalConfig() ?? false;
    }

    public function isEnabledForCompany(Company $company): bool
    {
        return (bool) ($company->settings?->orders_accept_paypal ?? false);
    }

    public function isReady(Company $company): bool
    {
        $settings = $company->settings;
        if ($settings && $settings->orders_collect_payment_enabled === false) {
            return false;
        }

        return PayPalService::isEnabled()
            && $this->isEnabledForCompany($company)
            && $this->isConfigured($company);
    }

    public function getInstructions(Company $company, ?Order $order = null): ?string
    {
        if ($order) {
            return 'Pay online securely via PayPal: '.$order->publicPayUrl();
        }

        return 'Pay online securely with PayPal, credit/debit card, or PayPal balance.';
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        /** @var \App\Services\OrderPaymentService $orderPaymentService */
        $orderPaymentService = app(\App\Services\OrderPaymentService::class);

        return $orderPaymentService->createPayPalPaymentLinkForOrder($order);
    }

    public function matchesCustomerInput(string $input, int $optionIndex = -1): bool
    {
        $lower = strtolower(trim($input));
        if ($optionIndex >= 0 && preg_match('/^\d+$/', $lower) && ((int) $lower - 1) === $optionIndex) {
            return true;
        }

        return (bool) preg_match('/\b(paypal|pay pal|cards|credit|debit)\b/i', $lower);
    }
}
