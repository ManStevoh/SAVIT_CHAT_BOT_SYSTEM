<?php

namespace App\Services\PaymentGateways\Drivers;

use App\Models\Company;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\PaymentGateways\Contracts\PaymentGatewayDriverInterface;
use App\Services\StripeService;

class StripeGatewayDriver implements PaymentGatewayDriverInterface
{
    public function getId(): string
    {
        return 'stripe';
    }

    public function getDisplayName(): string
    {
        return 'Card (Stripe)';
    }

    public function getCategory(): string
    {
        return 'digital';
    }

    public function getSortOrder(): int
    {
        return 20;
    }

    public function isReady(Company $company): bool
    {
        $settings = $company->settings;
        if ($settings && $settings->orders_collect_payment_enabled === false) {
            return false;
        }

        return (bool) ($settings && $settings->orders_accept_stripe && (StripeService::isEnabled() || $settings->hasOrderPaymentStripeConfig()));
    }

    public function getInstructions(Company $company, ?Order $order = null): ?string
    {
        if ($order) {
            return 'Pay online securely by card: '.$order->publicPayUrl();
        }

        return 'Pay online securely by Visa, Mastercard, or Apple Pay.';
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        /** @var OrderPaymentService $orderPaymentService */
        $orderPaymentService = app(OrderPaymentService::class);

        return $orderPaymentService->createStripePaymentLinkForOrder($order);
    }

    public function matchesCustomerInput(string $input, int $optionIndex = -1): bool
    {
        $lower = strtolower(trim($input));
        if ($optionIndex >= 0 && preg_match('/^\d+$/', $lower) && ((int) $lower - 1) === $optionIndex) {
            return true;
        }

        return (bool) preg_match('/\b(card|stripe|credit|debit|visa|mastercard|apple pay)\b/i', $lower);
    }
}
