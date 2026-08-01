<?php

namespace App\Services\PaymentGateways\Drivers;

use App\Models\Company;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\PaymentGateways\Contracts\PaymentGatewayDriverInterface;
use App\Services\PesapalService;

class PesapalGatewayDriver implements PaymentGatewayDriverInterface
{
    public function getId(): string
    {
        return 'pesapal';
    }

    public function getDisplayName(): string
    {
        return 'Pesapal (Cards, Mobile Money & Bank)';
    }

    public function getCategory(): string
    {
        return 'digital';
    }

    public function getSortOrder(): int
    {
        return 25;
    }

    public function isReady(Company $company): bool
    {
        $settings = $company->settings;
        if ($settings && $settings->orders_collect_payment_enabled === false) {
            return false;
        }

        return (bool) ($settings && $settings->orders_accept_pesapal && PesapalService::isEnabled() && $settings->hasOrderPaymentPesapalConfig());
    }

    public function getInstructions(Company $company, ?Order $order = null): ?string
    {
        if ($order) {
            return 'Pay online via Pesapal: '.$order->publicPayUrl();
        }

        return 'Pay online supporting M-Pesa, Airtel Money, Cards, and Bank Transfer via Pesapal.';
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        /** @var OrderPaymentService $orderPaymentService */
        $orderPaymentService = app(OrderPaymentService::class);

        return $orderPaymentService->createPesapalPaymentLinkForOrder($order);
    }

    public function matchesCustomerInput(string $input, int $optionIndex = -1): bool
    {
        $lower = strtolower(trim($input));
        if ($optionIndex >= 0 && preg_match('/^\d+$/', $lower) && ((int) $lower - 1) === $optionIndex) {
            return true;
        }

        return (bool) preg_match('/\b(pesapal|pesa pal|card|cards|mobile money)\b/i', $lower);
    }
}
