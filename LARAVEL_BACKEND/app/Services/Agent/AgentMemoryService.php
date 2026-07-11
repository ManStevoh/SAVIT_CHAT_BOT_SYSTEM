<?php

namespace App\Services\Agent;

use App\Models\AgentReflection;

final class AgentMemoryService
{
    public function getForPrompt(int $companyId): string
    {
        $reflections = AgentReflection::query()
            ->where('company_id', $companyId)
            ->whereIn('reflection_type', ['insight', 'improvement', 'pattern'])
            ->orderByDesc('updated_at')
            ->limit((int) config('agent.agent_reflection_limit', 10))
            ->get(['reflection_type', 'content']);

        if ($reflections->isEmpty()) {
            return '';
        }

        $lines = ['Agent learnings for this business:'];
        foreach ($reflections as $reflection) {
            $lines[] = "- ({$reflection->reflection_type}) {$reflection->content}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function store(
        int $companyId,
        ?int $chatId,
        string $type,
        string $content,
        ?array $metadata = null,
    ): AgentReflection {
        return AgentReflection::create([
            'company_id' => $companyId,
            'chat_id' => $chatId,
            'reflection_type' => mb_substr($type, 0, 40),
            'content' => mb_substr(trim($content), 0, 4000),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Lightweight post-turn reflection without extra LLM call.
     */
    public function reflectOnTurn(int $companyId, ?int $chatId, int $toolCallCount, bool $handoffRequested): void
    {
        if ($handoffRequested) {
            $this->store($companyId, $chatId, 'pattern', 'Customer requested human handoff — ensure agents respond promptly.');
        }

        if ($toolCallCount >= 5) {
            $this->store($companyId, $chatId, 'improvement', 'Complex query required many tools — consider adding FAQ or clearer catalog info.');
        }
    }
}
