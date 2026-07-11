<?php

namespace App\Services\Agent\Specialists;

use App\Jobs\Agent\RunCommerceSpecialistJob;
use App\Models\Chat;
use App\Models\CommerceAgentRun;
use App\Models\Company;
use App\Services\Agent\Specialists\Contracts\CommerceSpecialist;

/**
 * Multi-agent specialist orchestrator — Growth Engine pattern for Commerce.
 */
final class CommerceSpecialistOrchestrator
{
    /** @var array<string, CommerceSpecialist> */
    private array $specialists;

    public function __construct(
        SalesSpecialistService $sales,
        SupportSpecialistService $support,
        InventorySpecialistService $inventory,
    ) {
        $this->specialists = [
            'sales' => $sales,
            'support' => $support,
            'inventory' => $inventory,
        ];
    }

    /**
     * Sync consultation for live customer turns (feeds internal debate).
     *
     * @param  array<string, mixed>  $perception
     * @return array<string, string>
     */
    public function consultForTurn(
        Company $company,
        Chat $chat,
        string $incomingMessage,
        array $perception,
    ): array {
        if (! config('agent.specialists.consult_on_turn', true)) {
            return [];
        }

        $company->loadMissing('settings');
        if (! ($company->settings?->agent_council_enabled ?? false)) {
            return [];
        }

        $debate = [];
        foreach ($this->enabledTypes() as $type) {
            $specialist = $this->specialists[$type];
            $result = $specialist->consultForTurn($company, $chat, $incomingMessage, $perception);
            $debate[$type] = $result['perspective'] ?? '';
        }

        return array_filter($debate, fn ($v) => is_string($v) && trim($v) !== '');
    }

    /**
     * Async background pipeline (hourly / on-demand).
     *
     * @return list<array<string, mixed>>
     */
    public function dispatchBackgroundPipeline(Company $company, ?int $chatId = null, array $input = []): array
    {
        $runs = [];
        foreach ($this->enabledTypes() as $type) {
            $run = CommerceAgentRun::create([
                'company_id' => $company->id,
                'chat_id' => $chatId,
                'agent_type' => $type,
                'status' => 'pending',
                'input' => $input,
            ]);
            RunCommerceSpecialistJob::dispatch($run->id);
            $runs[] = $this->formatRun($run);
        }

        return $runs;
    }

    public function formatRun(CommerceAgentRun $run): array
    {
        return [
            'id' => (string) $run->id,
            'agentType' => $run->agent_type,
            'status' => $run->status,
            'chatId' => $run->chat_id,
            'input' => $run->input,
            'output' => $run->output,
            'startedAt' => $run->started_at?->toIso8601String(),
            'completedAt' => $run->completed_at?->toIso8601String(),
        ];
    }

    public function specialistForType(string $type): ?CommerceSpecialist
    {
        return $this->specialists[$type] ?? null;
    }

    /**
     * @return list<string>
     */
    private function enabledTypes(): array
    {
        $types = config('agent.specialists.types', ['sales', 'support', 'inventory']);

        return array_values(array_filter($types, fn ($t) => isset($this->specialists[$t])));
    }
}
