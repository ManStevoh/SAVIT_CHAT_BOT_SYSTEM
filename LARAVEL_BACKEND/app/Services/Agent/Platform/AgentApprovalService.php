<?php

namespace App\Services\Agent\Platform;

use App\Models\AgentActionRequest;
use App\Services\Platform\AuditService;

/**
 * Human approval workflows (#29) — risk-classified actions.
 */
final class AgentApprovalService
{
    public function __construct(
        protected AgentApprovalExecutionService $execution,
        protected CompanyPolicyService $policies,
        protected AuditService $audit,
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
        $request = AgentActionRequest::create([
            'company_id' => $companyId,
            'chat_id' => $chatId,
            'action_type' => mb_substr($actionType, 0, 80),
            'risk_level' => $riskLevel,
            'payload' => $payload,
            'reasoning' => $reasoning ? mb_substr($reasoning, 0, 2000) : null,
            'status' => 'pending',
        ]);

        $this->audit->log(
            'agent.approval.queued',
            AgentActionRequest::class,
            $request->id,
            null,
            ['action_type' => $actionType, 'risk_level' => $riskLevel],
            $companyId,
        );

        return $request;
    }

    /**
     * @return array{success: bool, request?: AgentActionRequest, result?: array<string, mixed>, message?: string}
     */
    public function approve(AgentActionRequest $request, \App\Models\User $approver): array
    {
        if ($request->company_id !== $approver->company_id) {
            return ['success' => false, 'message' => 'Unauthorized.'];
        }

        $policy = $this->policies->canApprove($approver, $request);
        if (! ($policy['allowed'] ?? false)) {
            return ['success' => false, 'message' => $policy['reason'] ?? 'Not authorized to approve.'];
        }

        $before = ['status' => $request->status];
        $result = $this->execution->execute($request, $approver);

        $this->audit->log(
            'agent.approval.approved',
            AgentActionRequest::class,
            $request->id,
            $before,
            ['status' => $request->fresh()->status, 'success' => $result['success'] ?? false],
            $request->company_id,
            $approver,
        );

        return $result;
    }

    public function reject(AgentActionRequest $request, \App\Models\User $approver, ?string $reason = null): AgentActionRequest
    {
        $before = ['status' => $request->status];
        $request->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'rejected_at' => now(),
            'execution_result' => $reason ? ['reason' => $reason] : null,
        ]);

        $this->audit->log(
            'agent.approval.rejected',
            AgentActionRequest::class,
            $request->id,
            $before,
            ['status' => 'rejected', 'reason' => $reason],
            $request->company_id,
            $approver,
        );

        return $request->fresh();
    }
}
