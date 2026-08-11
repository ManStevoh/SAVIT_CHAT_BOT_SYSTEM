<?php

namespace App\Services\PlatformPayments\Drivers;

use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\PlatformPayments\Contracts\PlatformPaymentDriverInterface;
use App\Services\PlatformPayments\ManualSubscriptionPaymentService;
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
            return ['success' => false, 'error' => 'Manual platform payment is not available. Ask an admin to enable Bank Transfer and set bank details.'];
        }

        $cfg = PaymentGateway::getConfig('manual');
        $invoiceRef = 'INV-'.strtoupper($plan->slug).'-'.date('Ymd').'-'.$company->id.'-'.strtoupper(substr(uniqid(), -4));

        $instructions = trim((string) ($cfg['instructions'] ?? ''));
        if ($instructions === '' && (! empty($cfg['bank_name']) || ! empty($cfg['account_number']))) {
            $instructions = sprintf(
                "Please transfer the plan fee to our bank account:\nBank: %s\nAccount Name: %s\nAccount Number: %s\nReference: %s",
                $cfg['bank_name'] ?? '',
                $cfg['account_name'] ?? '',
                $cfg['account_number'] ?? '',
                $invoiceRef
            );
        } elseif ($instructions !== '' && ! str_contains($instructions, $invoiceRef)) {
            $instructions .= "\n\nPayment reference: ".$invoiceRef;
        }

        $currency = strtoupper((string) ($cfg['currency'] ?? 'KES'));
        $amount = (float) (app(RegionalPricingService::class)->amountForPlan($plan, $currency) ?? $plan->price_amount ?? 0);

        $result = [
            'success' => true,
            'gateway' => 'manual',
            'invoice_reference' => $invoiceRef,
            'amount' => $amount,
            'currency' => $currency,
            'instructions' => $instructions,
            'bank_name' => $cfg['bank_name'] ?? null,
            'account_name' => $cfg['account_name'] ?? null,
            'account_number' => $cfg['account_number'] ?? null,
            'type' => 'manual_instructions',
        ];

        $payment = app(ManualSubscriptionPaymentService::class)->persistPending($company, $plan, $result);
        $result['payment_id'] = (string) $payment->id;

        return $result;
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
