<?php

namespace App\Services\Growth;

use App\Jobs\Growth\RunGrowthAgentJob;
use App\Models\Company;
use App\Models\GrowthAgentRun;
use App\Services\Growth\GrowthStrategyService;

class GrowthAgentOrchestrator
{
    public function dispatchPipeline(Company $company, array $input = []): array
    {
        $agents = ['research', 'strategy', 'content', 'analytics'];
        $runs = [];
        $mix = app(GrowthStrategyService::class)->buildContentMixPlan($company);
        $topMixCount = (int) ($mix['mix'][0]['count'] ?? 3);

        foreach ($agents as $agentType) {
            $agentInput = $input;
            if ($agentType === 'content') {
                $agentInput = array_merge($input, [
                    'fromWinners' => $input['fromWinners'] ?? true,
                    'count' => (int) ($input['count'] ?? $topMixCount),
                    'platform' => $input['platform'] ?? ($mix['platform'] ?? 'facebook'),
                ]);
            }

            $run = GrowthAgentRun::create([
                'company_id' => $company->id,
                'agent_type' => $agentType,
                'status' => 'pending',
                'input' => $agentInput,
            ]);
            RunGrowthAgentJob::dispatch($run->id);
            $runs[] = $this->formatRun($run);
        }

        return $runs;
    }

    public function formatRun(GrowthAgentRun $run): array
    {
        return [
            'id' => (string) $run->id,
            'agentType' => $run->agent_type,
            'status' => $run->status,
            'input' => $run->input,
            'output' => $run->output,
            'startedAt' => $run->started_at?->toIso8601String(),
            'completedAt' => $run->completed_at?->toIso8601String(),
        ];
    }
}
