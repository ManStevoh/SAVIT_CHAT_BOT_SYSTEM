<?php

namespace App\Services\PaymentGateways\Drivers;

use App\Models\Company;
use App\Models\Order;
use App\Services\PaymentGateways\Contracts\PaymentGatewayDriverInterface;

class ManualGatewayDriver implements PaymentGatewayDriverInterface
{
    public function getId(): string
    {
        return 'manual';
    }

    public function getDisplayName(): string
    {
        return 'Custom Manual Payment';
    }

    public function getCategory(): string
    {
        return 'manual';
    }

    public function getSortOrder(): int
    {
        return 50;
    }

    public function isReady(Company $company): bool
    {
        $settings = $company->settings;
        if ($settings && $settings->orders_collect_payment_enabled === false) {
            return false;
        }

        return (bool) ($settings && $settings->hasOrderPaymentManualInstructions());
    }

    public function getInstructions(Company $company, ?Order $order = null): ?string
    {
        $settings = $company->settings;
        if (! $settings || ! $settings->hasOrderPaymentManualInstructions()) {
            return null;
        }

        return trim((string) $settings->order_payment_manual_instructions);
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        $instructions = $this->getInstructions($order->company, $order);

        return [
            'success' => true,
            'manual_instructions' => $instructions,
            'message' => 'Pay using custom manual instructions.',
        ];
    }

    public function matchesCustomerInput(string $input, int $optionIndex = -1): bool
    {
        $lower = strtolower(trim($input));
        if ($optionIndex >= 0 && preg_match('/^\d+$/', $lower) && ((int) $lower - 1) === $optionIndex) {
            return true;
        }

        return (bool) preg_match('/\b(manual|custom|instructions|offline|till|bank|deposit|reference)\b/i', $lower);
    }
}
