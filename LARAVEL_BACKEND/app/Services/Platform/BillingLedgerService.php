<?php

namespace App\Services\Platform;

use App\Models\BillingPayment;
use App\Services\Platform\AuditService;

/**
 * Idempotent local billing ledger for subscription and order payments.
 */
final class BillingLedgerService
{
    public function __construct(
        protected AuditService $audit,
        protected DomainEventDispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $gateway,
        string $externalEventId,
        float $amount,
        ?int $companyId = null,
        ?int $subscriptionId = null,
        string $currency = 'USD',
        string $paymentType = 'subscription',
        ?string $externalPaymentId = null,
        array $metadata = [],
    ): BillingPayment {
        $payment = BillingPayment::firstOrCreate(
            [
                'gateway' => $gateway,
                'external_event_id' => $externalEventId,
            ],
            [
                'company_id' => $companyId,
                'subscription_id' => $subscriptionId,
                'external_payment_id' => $externalPaymentId,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'paid',
                'payment_type' => $paymentType,
                'metadata' => $metadata !== [] ? $metadata : null,
                'paid_at' => now(),
            ],
        );

        if ($payment->wasRecentlyCreated && $companyId) {
            $this->audit->log(
                'billing.payment.recorded',
                BillingPayment::class,
                $payment->id,
                null,
                ['gateway' => $gateway, 'amount' => $amount, 'currency' => $currency],
                $companyId,
            );

            $this->events->dispatch('payment.received', [
                'payment_id' => $payment->id,
                'gateway' => $gateway,
                'amount' => $amount,
                'currency' => $currency,
            ], $companyId);
        }

        return $payment;
    }
}
