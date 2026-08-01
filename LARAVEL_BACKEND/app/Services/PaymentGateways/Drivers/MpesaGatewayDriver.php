<?php

namespace App\Services\PaymentGateways\Drivers;

use App\Models\Company;
use App\Models\Order;
use App\Services\MpesaService;
use App\Services\OrderPaymentService;
use App\Services\PaymentGateways\Contracts\PaymentGatewayDriverInterface;

class MpesaGatewayDriver implements PaymentGatewayDriverInterface
{
    public function getId(): string
    {
        return 'mpesa';
    }

    public function getDisplayName(): string
    {
        return 'M-Pesa (STK Push)';
    }

    public function getCategory(): string
    {
        return 'digital';
    }

    public function getSortOrder(): int
    {
        return 10;
    }

    public function isReady(Company $company): bool
    {
        $settings = $company->settings;
        if ($settings && $settings->orders_collect_payment_enabled === false) {
            return false;
        }

        return (bool) ($settings && $settings->orders_accept_mpesa && MpesaService::isEnabled() && $settings->hasOrderPaymentMpesaConfig());
    }

    public function getInstructions(Company $company, ?Order $order = null): ?string
    {
        return 'M-Pesa payment prompt sent to your phone.';
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        $phone = $options['phone'] ?? $order->customer_phone ?? '';
        /** @var OrderPaymentService $orderPaymentService */
        $orderPaymentService = app(OrderPaymentService::class);

        return $orderPaymentService->sendStkPushForOrder($order, (string) $phone);
    }

    public function matchesCustomerInput(string $input, int $optionIndex = -1): bool
    {
        $lower = strtolower(trim($input));
        if ($optionIndex >= 0 && preg_match('/^\d+$/', $lower) && ((int) $lower - 1) === $optionIndex) {
            return true;
        }

        return (bool) preg_match('/\b(mpesa|m-pesa|stk|lipa|till|paybill|mobile money)\b/i', $lower);
    }
}
