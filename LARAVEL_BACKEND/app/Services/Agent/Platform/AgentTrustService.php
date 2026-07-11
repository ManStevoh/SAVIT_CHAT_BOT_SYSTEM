<?php

namespace App\Services\Agent\Platform;

use App\Models\AgentTrustLog;

/**
 * AI Trust Layer (#33) — auditable, explainable decisions.
 */
final class AgentTrustService
{
    /**
     * @param  list<string>  $toolsUsed
     * @param  array<string, mixed>  $dataConsulted
     * @param  array<string, mixed>  $explainability
     */
    public function logDecision(
        int $companyId,
        ?int $chatId,
        string $actionType,
        ?string $goal,
        ?string $reasoningSummary,
        array $toolsUsed = [],
        array $dataConsulted = [],
        ?float $confidence = null,
        ?string $outcome = null,
        array $explainability = [],
    ): AgentTrustLog {
        return AgentTrustLog::create([
            'company_id' => $companyId,
            'chat_id' => $chatId,
            'action_type' => mb_substr($actionType, 0, 80),
            'goal' => $goal ? mb_substr($goal, 0, 120) : null,
            'reasoning_summary' => $reasoningSummary ? mb_substr($reasoningSummary, 0, 2000) : null,
            'tools_used' => $toolsUsed,
            'data_consulted' => $dataConsulted,
            'confidence' => $confidence,
            'outcome' => $outcome ? mb_substr($outcome, 0, 40) : null,
            'explainability' => $explainability,
            'created_at' => now(),
        ]);
    }
}
