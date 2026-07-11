<?php

namespace App\Services\Agent\Platform;

use App\Models\AgentActionRequest;

/**
 * Human approval workflows (#29) — risk-classified actions.
 */
final class AgentApprovalService
{
    public function __construct(
        protected AgentApprovalExecutionService $execution,
    ) {}
    public function riskLevelForTool(string $toolName): string
    {
        $levels = config('agent.platform.tool_risk_levels', []);

        return $levels[$toolName] ?? 'low';
    }

    public function requiresApproval(string $toolName): bool
    {
        return $this->riskLevelForTool($toolName) === 'high';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queue(
        int $companyId,
        ?int $chatId,
        string $actionType,
        string $riskLevel,
        array $payload,
        ?string $reasoning = null,
    ): AgentActionRequest {
        return AgentActionRequest::create([
            'company_id' => $companyId,
            'chat_id' => $chatId,
            'action_type' => mb_substr($actionType, 0, 80),
            'risk_level' => $riskLevel,
            'payload' => $payload,
            'reasoning' => $reasoning ? mb_substr($reasoning, 0, 2000) : null,
            'status' => 'pending',
        ]);
    }

    /**
     * @return array{success: bool, request?: AgentActionRequest, result?: array<string, mixed>, message?: string}
     */
    public function approve(AgentActionRequest $request, \App\Models\User $approver): array
    {
        if ($request->company_id !== $approver->company_id) {
            return ['success' => false, 'message' => 'Unauthorized.'];
        }

        return $this->execution->execute($request, $approver);
    }

    public function reject(AgentActionRequest $request, \App\Models\User $approver, ?string $reason = null): AgentActionRequest
    {
        $request->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'rejected_at' => now(),
            'execution_result' => $reason ? ['reason' => $reason] : null,
        ]);

        return $request->fresh();
    }
}
