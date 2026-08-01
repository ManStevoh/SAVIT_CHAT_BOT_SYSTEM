<?php

namespace App\Services\PaymentGateways\Drivers;

use App\Models\Company;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\PaymentGateways\Contracts\PaymentGatewayDriverInterface;
use App\Services\PaystackService;

class PaystackGatewayDriver implements PaymentGatewayDriverInterface
{
    public function getId(): string
    {
        return 'paystack';
    }

    public function getDisplayName(): string
    {
        return 'Paystack';
    }

    public function getCategory(): string
    {
        return 'digital';
    }

    public function getSortOrder(): int
    {
        return 30;
    }

    public function isReady(Company $company): bool
    {
        $settings = $company->settings;
        if ($settings && $settings->orders_collect_payment_enabled === false) {
            return false;
        }

        return (bool) ($settings && $settings->orders_accept_paystack && PaystackService::isEnabled() && $settings->hasOrderPaymentPaystackConfig());
    }

    public function getInstructions(Company $company, ?Order $order = null): ?string
    {
        if ($order) {
            return 'Pay online via Paystack: '.$order->publicPayUrl();
        }

        return 'Pay online supporting cards, bank transfers, and mobile money via Paystack.';
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        /** @var OrderPaymentService $orderPaymentService */
        $orderPaymentService = app(OrderPaymentService::class);

        return $orderPaymentService->createPaystackPaymentLinkForOrder($order);
    }

    public function matchesCustomerInput(string $input, int $optionIndex = -1): bool
    {
        $lower = strtolower(trim($input));
        if ($optionIndex >= 0 && preg_match('/^\d+$/', $lower) && ((int) $lower - 1) === $optionIndex) {
            return true;
        }

        return (bool) preg_match('/\b(paystack)\b/i', $lower);
    }
}
