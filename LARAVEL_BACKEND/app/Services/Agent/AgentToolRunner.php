<?php

namespace App\Services\Agent;

use App\Models\AgentToolInvocation;
use App\Services\Agent\Platform\AgentApprovalService;
use App\Services\Agent\Platform\ExternalModuleToolBridge;
use Illuminate\Support\Facades\Log;

final class AgentToolRunner
{
    public function __construct(
        protected AgentToolRegistry $registry,
        protected AgentApprovalService $approval,
        protected ExternalModuleToolBridge $externalTools,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(string $toolName, AgentToolContext $context, array $arguments): array
    {
        if ($this->approval->requiresApproval($toolName)) {
            $this->approval->queue(
                companyId: (int) $context->company->id,
                chatId: (int) $context->chat->id,
                actionType: $toolName,
                riskLevel: 'high',
                payload: ['arguments' => $arguments],
                reasoning: 'High-risk tool blocked pending human approval.',
            );

            return [
                'pending_approval' => true,
                'message' => 'This action requires owner approval before execution.',
            ];
        }

        $started = microtime(true);
        $success = true;
        $result = [];

        try {
            $result = $this->registry->execute($toolName, $context, $arguments);
        } catch (\Throwable $e) {
            if ($this->externalTools->canExecute($context->company, $toolName)) {
                $result = $this->externalTools->execute($context->company, $toolName, $context, $arguments);
                $success = ! isset($result['error']);
            } else {
                $success = false;
                $result = ['error' => mb_substr($e->getMessage(), 0, 500)];
                Log::warning('Agent tool execution failed', [
                    'tool' => $toolName,
                    'company_id' => $context->company->id,
                    'chat_id' => $context->chat->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $maxChars = (int) config('agent.tool_result_max_chars', 8000);
        $result = $this->truncateResult($result, $maxChars);

        try {
            AgentToolInvocation::create([
                'company_id' => $context->company->id,
                'chat_id' => $context->chat->id,
                'tool_name' => $toolName,
                'arguments' => $arguments,
                'result' => $result,
                'duration_ms' => $durationMs,
                'success' => $success,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist agent tool invocation', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function truncateResult(array $result, int $maxChars): array
    {
        $json = json_encode($result, JSON_UNESCAPED_UNICODE);
        if ($json === false || strlen($json) <= $maxChars) {
            return $result;
        }

        return [
            'truncated' => true,
            'preview' => mb_substr($json, 0, $maxChars - 20).'…',
        ];
    }
}
