<?php

namespace App\Services\Agent\Intelligence;

use App\Models\BusinessProbabilityScore;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;

/**
 * ABI Level 4 — calibrated buy / churn / refund probability heuristics.
 */
final class BusinessProbabilityService
{
    /**
     * @return array{buy: float, churn: float, refund: float, factors: array<string, mixed>}
     */
    public function computeForCompany(Company $company): array
