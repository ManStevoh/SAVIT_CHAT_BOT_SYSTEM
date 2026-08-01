<?php

namespace App\Services\PaymentGateways\Drivers;

use App\Models\Company;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\PaymentGateways\Contracts\PaymentGatewayDriverInterface;

class CodGatewayDriver implements PaymentGatewayDriverInterface
{
    public function getId(): string
    {
        return 'cod';
    }

    public function getDisplayName(): string
    {
        return 'Cash on Delivery (COD)';
    }

    public function getCategory(): string
    {
        return 'offline';
    }

    public function getSortOrder(): int
    {
        return 40;
    }

    public function isReady(Company $company): bool
    {
        $settings = $company->settings;
        if ($settings && $settings->orders_collect_payment_enabled === false) {
            return false;
        }

        return (bool) ($settings && $settings->orders_accept_cod);
    }

    public function getInstructions(Company $company, ?Order $order = null): ?string
    {
        return 'Pay cash when your order arrives. Order is confirmed immediately.';
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        /** @var OrderPaymentService $orderPaymentService */
        $orderPaymentService = app(OrderPaymentService::class);

        return $orderPaymentService->confirmCodOrder($order);
    }

    public function matchesCustomerInput(string $input, int $optionIndex = -1): bool
    {
        $lower = strtolower(trim($input));
        if ($optionIndex >= 0 && preg_match('/^\d+$/', $lower) && ((int) $lower - 1) === $optionIndex) {
            return true;
        }

        return (bool) preg_match('/\b(cod|cash|cash on delivery|delivery|arrival|pay on delivery)\b/i', $lower);
    }
}
