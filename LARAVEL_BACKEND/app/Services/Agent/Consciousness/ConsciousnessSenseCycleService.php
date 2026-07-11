<?php

namespace App\Services\Agent\Consciousness;

use App\Models\Company;
use App\Services\Agent\Brain\UnifiedCompanyBrainService;
use App\Services\Agent\Events\CommerceEventDetector;
use App\Services\Agent\Timeline\BusinessTimelineService;
use Illuminate\Support\Facades\Log;

/**
 * Phase 9 — lightweight 5-minute consciousness sense cycle.
 */
final class ConsciousnessSenseCycleService
{
    public function __construct(
        protected UnifiedCompanyBrainService $brain,
        protected BusinessTimelineService $timeline,
        protected CommerceEventDetector $events,
    ) {}

    /**
     * @return array<string, int|bool>
     */
    public function sense(Company $company): array
    {
        $company->loadMissing('settings');
        if (! ($company->settings?->agent_commerce_enabled ?? false)) {
            return ['skipped' => true];
        }
