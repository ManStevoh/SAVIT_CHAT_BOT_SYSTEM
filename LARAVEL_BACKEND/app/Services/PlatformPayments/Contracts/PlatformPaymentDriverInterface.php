<?php

namespace App\Services\PlatformPayments\Contracts;

use App\Models\Company;
use App\Models\Plan;

interface PlatformPaymentDriverInterface
{
    /** Unique gateway identifier (e.g. 'stripe', 'paystack', 'mpesa', 'manual') */
    public function getId(): string;

    /** Human-readable display name */
    public function getDisplayName(): string;

    /** Priority order for rendering */
    public function getSortOrder(): int;

    /** Whether gateway is enabled systemwide and has platform credentials configured */
    public function isAvailable(): bool;

    /** Initiate platform plan payment / checkout session */
    public function initiatePlanPayment(Company $company, Plan $plan, array $options = []): array;

    /** Additional metadata for frontend/API response (e.g. currency, instructions) */
    public function getMetadata(): array;
}
