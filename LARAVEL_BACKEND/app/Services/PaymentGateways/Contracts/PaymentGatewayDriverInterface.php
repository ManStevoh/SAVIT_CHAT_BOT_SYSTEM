<?php

namespace App\Services\PaymentGateways\Contracts;

use App\Models\Company;
use App\Models\Order;

interface PaymentGatewayDriverInterface
{
    /** Unique gateway identifier, e.g. 'mpesa', 'stripe', 'paystack', 'cod', 'manual' */
    public function getId(): string;

    /** Human-friendly name, e.g. 'M-Pesa (STK Push)' */
    public function getDisplayName(): string;

    /** Category: 'digital', 'offline', 'manual' */
    public function getCategory(): string;

    /** Sort order for deterministic option numbering */
    public function getSortOrder(): int;

    /** Is this payment gateway active and ready for this tenant? Checks master toggle + tenant switch + credentials */
    public function isReady(Company $company): bool;

    /** Customer-facing instructions or pay link if available */
    public function getInstructions(Company $company, ?Order $order = null): ?string;

    /** Initiate payment for an order (e.g. STK push, checkout session URL, or manual confirmation) */
    public function initiatePayment(Order $order, array $options = []): array;

    /** Check if customer input text/number matches this gateway option */
    public function matchesCustomerInput(string $input, int $optionIndex = -1): bool;
}
