<?php

namespace App\Services\AI\Drivers\Contracts;

use App\Services\AI\OpenAiChatResult;
use App\Services\AI\ResolvedAiModel;

/**
 * Drivers that support tool/function calling for the commerce agent loop.
 */
interface SupportsToolCalling
{
    /**
     * @param  array<int, array<string, mixed>>  $messages  OpenAI-style messages (incl. tool / tool_calls)
     * @param  array<int, array<string, mixed>>  $tools     OpenAI-style tool definitions
     */
    public function chatCompletionWithTools(
        ResolvedAiModel $resolved,
        array $messages,
        array $tools,
        int $maxTokens,
        ?float $temperature,
        int $timeoutSeconds,
    ): OpenAiChatResult;
}
