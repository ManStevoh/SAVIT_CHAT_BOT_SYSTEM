<?php

namespace App\Services\PlatformPayments\Drivers;

use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\PlatformPayments\Contracts\PlatformPaymentDriverInterface;
use App\Services\RegionalPricingService;

class PlatformManualDriver implements PlatformPaymentDriverInterface
{
    public function getId(): string
    {
        return 'manual';
    }

    public function getDisplayName(): string
    {
        return 'Bank Transfer / Invoice';
    }

    public function getSortOrder(): int
    {
        return 40;
    }

    public function isAvailable(): bool
    {
        if (! PaymentGateway::isEnabled('manual')) {
            return false;
        }

        $cfg = PaymentGateway::getConfig('manual');

        return ! empty($cfg['instructions']) || ! empty($cfg['bank_name']) || ! empty($cfg['account_number']);
    }

    public function initiatePlanPayment(Company $company, Plan $plan, array $options = []): array
    {
        if (! $this->isAvailable()) {
            return ['success' => false, 'error' => 'Manual platform payment is not available.'];
        }

        $cfg = PaymentGateway::getConfig('manual');
        $invoiceRef = 'INV-'.strtoupper($plan->slug).'-'.date('Ymd').'-'.$company->id;

        $instructions = $cfg['instructions'] ?? '';
        if (! $instructions && (! empty($cfg['bank_name']) || ! empty($cfg['account_number']))) {
            $instructions = sprintf(
                "Please transfer the plan fee to our bank account:\nBank: %s\nAccount Name: %s\nAccount Number: %s\nReference: %s",
                $cfg['bank_name'] ?? 'Super Admin Bank',
                $cfg['account_name'] ?? 'EssemChat Platform',
                $cfg['account_number'] ?? '',
                $invoiceRef
            );
        }

        $currency = strtoupper((string) ($cfg['currency'] ?? 'KES'));
        $amount = (float) (app(RegionalPricingService::class)->amountForPlan($plan, $currency) ?? $plan->price_amount ?? 0);

        return [
            'success' => true,
            'gateway' => 'manual',
            'invoice_reference' => $invoiceRef,
            'amount' => $amount,
            'currency' => $currency,
            'instructions' => $instructions,
            'type' => 'manual_instructions',
        ];
    }

    public function getMetadata(): array
    {
        $cfg = PaymentGateway::getConfig('manual');

        return [
            'currency' => strtoupper((string) ($cfg['currency'] ?? 'KES')),
            'env' => $cfg['env'] ?? 'sandbox',
            'bank_name' => $cfg['bank_name'] ?? null,
            'account_name' => $cfg['account_name'] ?? null,
            'account_number' => $cfg['account_number'] ?? null,
            'instructions' => $cfg['instructions'] ?? null,
            'supports_subscription_auto_renew' => false,
        ];
    }
}
