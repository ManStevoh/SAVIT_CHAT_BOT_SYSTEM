<?php

namespace App\Services\Agent\Cognitive;

use App\Models\StrategicMemory;

/**
 * Strategic memory (#42) — remember tactics, not just chats.
 */
final class StrategicMemoryService
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function store(
        int $companyId,
        string $strategyType,
        string $title,
        string $contextSummary,
        string $outcomeSummary,
        int $successScore = 70,
        array $evidence = [],
    ): StrategicMemory {
        return StrategicMemory::create([
            'company_id' => $companyId,
            'strategy_type' => $strategyType,
            'title' => $title,
            'context_summary' => $contextSummary,
            'outcome_summary' => $outcomeSummary,
            'success_score' => max(0, min(100, $successScore)),
            'evidence' => $evidence,
        ]);
    }

    public function getForPrompt(int $companyId, int $limit = 6): string
    {
        $items = StrategicMemory::query()
            ->where('company_id', $companyId)
            ->orderByDesc('success_score')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['strategy_type', 'title', 'outcome_summary', 'success_score']);

        if ($items->isEmpty()) {
            return '';
        }

        $parts = ['Strategic memory (proven tactics — internal):'];
        foreach ($items as $item) {
            $parts[] = "- [{$item->strategy_type}] {$item->title}: {$item->outcome_summary} (success {$item->success_score}%)";
        }

        return implode("\n", $parts);
    }
}
