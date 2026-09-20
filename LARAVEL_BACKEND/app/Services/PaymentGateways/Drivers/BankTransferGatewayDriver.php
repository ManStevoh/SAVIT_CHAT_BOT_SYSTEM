<?php

namespace App\Services\PaymentGateways\Drivers;

use App\Models\Company;
use App\Models\Order;
use App\Services\PaymentGateways\Contracts\PaymentGatewayDriverInterface;

class BankTransferGatewayDriver implements PaymentGatewayDriverInterface
{
    public function getId(): string
    {
        return 'bank_transfer';
    }

    public function getDisplayName(): string
    {
        return 'Bank transfer';
    }

    public function getCategory(): string
    {
        return 'manual';
    }

    public function getSortOrder(): int
    {
        return 45;
    }

    public function isReady(Company $company): bool
    {
        $settings = $company->settings;
        if ($settings && $settings->orders_collect_payment_enabled === false) {
            return false;
        }

        $instructions = is_string($settings?->bank_transfer_instructions)
            ? trim($settings->bank_transfer_instructions)
            : '';
        if ($instructions === '') {
            return false;
        }

        return (bool) ($settings?->orders_accept_bank_transfer ?? true);
    }

    public function getInstructions(Company $company, ?Order $order = null): ?string
    {
        $settings = $company->settings;
        $instructions = is_string($settings?->bank_transfer_instructions)
            ? trim($settings->bank_transfer_instructions)
            : '';

        return $instructions !== '' ? $instructions : null;
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        $instructions = $this->getInstructions($order->company, $order);

        return [
            'success' => true,
            'manual_instructions' => $instructions,
            'message' => 'Pay by bank transfer using the details below, then tap “I’ve paid”.',
        ];
    }

    public function matchesCustomerInput(string $input, int $optionIndex = -1): bool
    {
        $lower = strtolower(trim($input));
        if ($optionIndex >= 0 && preg_match('/^\d+$/', $lower) && ((int) $lower - 1) === $optionIndex) {
            return true;
        }

        return (bool) preg_match('/\b(bank|transfer|deposit|eft|rtgs|account)\b/i', $lower);
    }
}
