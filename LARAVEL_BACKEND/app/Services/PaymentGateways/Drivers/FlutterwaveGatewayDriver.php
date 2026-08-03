<?php

namespace App\Services\PaymentGateways\Drivers;

use App\Models\Company;
use App\Models\Order;
use App\Services\FlutterwaveService;
use App\Services\PaymentGateways\Contracts\PaymentGatewayDriverInterface;

class FlutterwaveGatewayDriver implements PaymentGatewayDriverInterface
{
    public function getId(): string
    {
        return 'flutterwave';
    }

    public function getDisplayName(): string
    {
        return 'Flutterwave (Cards, Mobile Money & Bank Transfer)';
    }

    public function getCategory(): string
    {
        return 'card_online';
    }

    public function getSortOrder(): int
    {
        return 35;
    }

    public function isConfigured(Company $company): bool
    {
        return $company->settings?->hasOrderPaymentFlutterwaveConfig() ?? false;
    }

    public function isEnabledForCompany(Company $company): bool
    {
        return (bool) ($company->settings?->orders_accept_flutterwave ?? false);
    }

    public function isReady(Company $company): bool
    {
        return FlutterwaveService::isEnabled()
            && $this->isEnabledForCompany($company)
            && $this->isConfigured($company);
    }

    public function getInstructions(Company $company, ?Order $order = null): ?string
    {
        if ($order) {
            return 'Pay online via Flutterwave: '.$order->publicPayUrl();
        }

        return 'Pay online via Flutterwave supporting cards, mobile money, and bank transfer options.';
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        /** @var \App\Services\OrderPaymentService $orderPaymentService */
        $orderPaymentService = app(\App\Services\OrderPaymentService::class);

        return $orderPaymentService->createFlutterwavePaymentLinkForOrder($order);
    }

    public function matchesCustomerInput(string $input, int $optionIndex = -1): bool
    {
        $lower = strtolower(trim($input));
        if ($optionIndex >= 0 && preg_match('/^\d+$/', $lower) && ((int) $lower - 1) === $optionIndex) {
            return true;
        }

        return (bool) preg_match('/\b(flutterwave|flutter wave|card|cards|mobile money)\b/i', $lower);
    }
}
