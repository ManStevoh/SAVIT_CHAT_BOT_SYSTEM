<?php

namespace App\Services\AI;

use App\Models\Company;

/**
 * Backward-compatible facade over the multi-provider AiGateway.
 */
class OpenAiClient
{
    public const USE_CASE_WHATSAPP = 'whatsapp';

    public const USE_CASE_GROWTH = 'growth';

    public const USE_CASE_EMBEDDING = 'embedding';

    public function __construct(
        protected AiGateway $gateway,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chatCompletion(
        array $messages,
        string $useCase,
        ?int $companyId = null,
        ?int $chatId = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        int $timeoutSeconds = 30,
        bool $jsonMode = false,
    ): OpenAiChatResult {
        $company = $companyId ? Company::find($companyId) : null;

        return $this->gateway->chatCompletion(
            messages: $messages,
            useCase: $useCase,
            company: $company,
            chatId: $chatId,
            maxTokens: $maxTokens,
            temperature: $temperature,
            timeoutSeconds: $timeoutSeconds,
            jsonMode: $jsonMode,
        );
    }

    /**
     * @return array<int, float>|null
     */
    public function embedText(string $text, ?int $companyId = null): ?array
    {
        return $this->gateway->embedText($text, $companyId);
    }
}
