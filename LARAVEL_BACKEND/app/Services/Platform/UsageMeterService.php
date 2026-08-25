<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\UsageMeter;
use Carbon\Carbon;

final class UsageMeterService
{
    public function __construct(
        protected EntitlementService $entitlements,
    ) {}

    public function increment(Company $company, string $meterKey, int $amount = 1): UsageMeter
    {
        $meter = $this->currentMeter($company, $meterKey);
        $meter->increment('consumed', $amount);

        return $meter->fresh();
    }

    public function consumed(Company $company, string $meterKey): int
    {
        return (int) $this->currentMeter($company, $meterKey)->consumed;
    }

    public function limit(Company $company, string $meterKey): ?int
    {
        $limits = $this->entitlements->limitsForCompany($company);

        return match ($meterKey) {
            'messages' => array_key_exists('messages', $limits) && $limits['messages'] === null
                ? null
                : (int) ($limits['messages'] ?? 5000),
            'team' => (int) ($limits['team'] ?? 3),
            default => null,
        };
    }

    public function isWithinLimit(Company $company, string $meterKey): bool
    {
        $limit = $this->limit($company, $meterKey);
        if ($limit === null) {
            return true;
        }

        return $this->consumed($company, $meterKey) < $limit;
    }

    private function currentMeter(Company $company, string $meterKey): UsageMeter
    {
        $period = $this->entitlements->currentBillingPeriod($company);
        $start = $period['start']->toDateString();

        $existing = UsageMeter::query()
            ->where('company_id', $company->id)
            ->where('meter_key', $meterKey)
            ->whereDate('period_start', $start)
            ->first();

        if ($existing) {
            return $existing;
        }

        return UsageMeter::create([
            'company_id' => $company->id,
            'meter_key' => $meterKey,
            'period_start' => $start,
            'period_end' => $period['end']->toDateString(),
            'consumed' => 0,
            'limit_value' => $this->limit($company, $meterKey),
        ]);
    }
}
