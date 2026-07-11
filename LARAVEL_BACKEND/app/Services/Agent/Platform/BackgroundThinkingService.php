<?php

namespace App\Services\Agent\Platform;

use App\Models\Chat;
use App\Models\Company;
use App\Services\Agent\Brain\UnifiedCompanyBrainService;
use App\Services\Agent\Graph\BusinessGraphV2Service;
use App\Services\Agent\Timeline\BusinessTimelineService;
use Illuminate\Support\Facades\Log;

/**
 * Continuous background thinking after customer leaves (#20).
 */
final class BackgroundThinkingService
{
    public function __construct(
        protected BusinessWorldModelService $worldModel,
        protected OpportunityDetectionService $opportunities,
        protected ExecutiveBriefService $executive,
        protected UnifiedCompanyBrainService $companyBrain,
        protected BusinessTimelineService $timeline,
        protected BusinessGraphV2Service $graph,
    ) {}

    public function processCompany(Company $company): array
    {
        $company->loadMissing('settings');
        if (! ($company->settings?->agent_commerce_enabled ?? false)) {
            return ['snapshots' => 0, 'opportunities' => 0];
        }

        $this->worldModel->snapshot($company, 'background_thinking');
        $opps = $this->opportunities->detectForCompany($company);
        if (config('agent.brain.enabled', true)) {
            $this->companyBrain->refreshIfStale($company, (int) config('agent.brain.snapshot_max_age_minutes', 60));
        }

        if (config('agent.timeline.sync_on_background', true)) {
            $this->timeline->syncFromCompany($company, 50);
        }
        if (config('agent.graph.sync_on_background', true)) {
            $this->graph->syncFromCompany($company);
        }

        return [
            'snapshots' => 1,
            'opportunities' => count($opps),
        ];
    }

    public function processAfterChat(Company $company, Chat $chat): void
    {
        try {
            $this->processCompany($company);
        } catch (\Throwable $e) {
            Log::warning('Background thinking failed', [
                'company_id' => $company->id,
                'chat_id' => $chat->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
