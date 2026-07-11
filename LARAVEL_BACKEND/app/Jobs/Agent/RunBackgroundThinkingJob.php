<?php

namespace App\Jobs\Agent;

use App\Models\Company;
use App\Services\Agent\Platform\BackgroundThinkingService;
use App\Services\Agent\Platform\BusinessHealthScoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunBackgroundThinkingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $companyId = null, public ?int $chatId = null) {}

    public function handle(
        BackgroundThinkingService $thinking,
        BusinessHealthScoreService $health,
        \App\Services\Agent\Cognitive\ToolProposalService $toolProposals,
        \App\Services\Agent\Cognitive\KnowledgeCompressionService $knowledge,
    ): void {
        $query = Company::query()
            ->where('status', 'active')
            ->whereHas('settings', fn ($q) => $q->where('agent_commerce_enabled', true));

        if ($this->companyId) {
            $query->where('id', $this->companyId);
        }

        foreach ($query->pluck('id') as $companyId) {
            $company = Company::with('settings')->find($companyId);
            if (! $company) {
                continue;
            }
            $thinking->processCompany($company);
            $health->computeForCompany($company);
            $toolProposals->detectForCompany((int) $companyId);
            $knowledge->compressForCompany($company);

            if (config('agent.specialists.background_enabled', true)) {
                app(\App\Services\Agent\Specialists\CommerceSpecialistOrchestrator::class)
                    ->dispatchBackgroundPipeline($company, $this->chatId);
            }
            if (config('agent.events.detection_enabled', true)) {
                app(\App\Services\Agent\Events\CommerceEventDetector::class)->detectForCompany($company);
            }
        }
    }
}
