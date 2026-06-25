<?php

namespace App\Services\AI\Drivers\Contracts;

use App\Services\AI\EmbedResult;
use App\Services\AI\OpenAiChatResult;
use App\Services\AI\ResolvedAiModel;

interface AiProviderDriver
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chatCompletion(
        ResolvedAiModel $resolved,
        array $messages,
        int $maxTokens,
        ?float $temperature,
        bool $jsonMode,
        int $timeoutSeconds,
    ): OpenAiChatResult;

    /**
     * @return EmbedResult|null
     */
    public function embed(ResolvedAiModel $resolved, string $text, int $timeoutSeconds = 30): ?EmbedResult;
}
