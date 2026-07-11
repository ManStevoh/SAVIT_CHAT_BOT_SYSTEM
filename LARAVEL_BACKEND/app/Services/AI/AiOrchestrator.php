<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\Company;
use App\Services\AI\Drivers\OpenAiDriver;
use App\Services\Agent\AgentChatService;
use App\Services\AI\Classification\EntityExtractionService;
use App\Services\AI\Classification\IntentClassificationService;

/**
 * Unified AI orchestration facade — route use cases to the right model capability.
 *
 * Application code should depend on this service instead of calling providers directly.
 */
final class AiOrchestrator
{
    public function __construct(
        protected AiGateway $gateway,
        protected AgentChatService $agentChat,
        protected IntentClassificationService $intents,
        protected EntityExtractionService $entities,
    ) {}

    /**
     * Customer or general chat — auto-routes trivial messages to fast_chat.
     *
     * @param  array<int, array{role: string, content: string|array<int, mixed>}>  $messages
     */
    public function chat(
        array $messages,
        Company $company,
        string $useCase = AiUseCase::WHATSAPP,
        ?int $chatId = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        int $timeoutSeconds = 30,
        bool $jsonMode = false,
        ?string $latestUserMessage = null,
    ): OpenAiChatResult {
        $resolvedUseCase = $useCase;
        if ($latestUserMessage !== null && $this->intents->isTrivialMessage($latestUserMessage)) {
            $resolvedUseCase = AiUseCase::WHATSAPP_FAST;
        }

        return $this->gateway->chatCompletion(
            messages: $messages,
            useCase: $resolvedUseCase,
            company: $company,
            chatId: $chatId,
            maxTokens: $maxTokens,
            temperature: $temperature,
            timeoutSeconds: $timeoutSeconds,
            jsonMode: $jsonMode,
        );
    }

    /**
     * Deep reasoning (planning, business decisions, JSON traces).
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function reason(
        array $messages,
        Company $company,
        ?int $chatId = null,
        ?int $maxTokens = 600,
        ?float $temperature = 0.3,
        int $timeoutSeconds = 25,
    ): OpenAiChatResult {
        return $this->gateway->chatCompletion(
            messages: $messages,
            useCase: AiUseCase::AGENT_REASONING,
            company: $company,
            chatId: $chatId,
            maxTokens: $maxTokens,
            temperature: $temperature,
            timeoutSeconds: $timeoutSeconds,
            jsonMode: true,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function completeWithTools(
        array $messages,
        array $tools,
        Company $company,
        ?int $chatId = null,
        ?int $maxTokens = null,
        ?float $temperature = 0.4,
        int $timeoutSeconds = 35,
    ): OpenAiChatResult {
        return $this->agentChat->completeWithTools(
            $messages,
            $tools,
            $company,
            $chatId,
            $maxTokens,
            $temperature,
            $timeoutSeconds,
            AiUseCase::AGENT_COMMERCE,
        );
    }

    public function vision(
        Company $company,
        string $imageUrl,
        string $instruction,
        ?int $chatId = null,
        bool $jsonMode = true,
        int $timeoutSeconds = 40,
    ): OpenAiChatResult {
        return $this->agentChat->completeWithVision(
            $company,
            $imageUrl,
            $instruction,
            $chatId,
            $jsonMode,
            $timeoutSeconds,
        );
    }

    /**
     * @return array<int, float>|null
     */
    public function embed(string $text, ?Company $company = null): ?array
    {
        return $this->gateway->embedText($text, $company?->id);
    }

    public function transcribe(string $filePath, string $filename, Company $company): ?string
    {
        $result = $this->gateway->transcribeAudio($filePath, $filename, $company);

        return $result->success ? $result->text : null;
    }

    public function synthesize(string $text, Company $company): SynthesizeResult
    {
        return $this->gateway->synthesizeSpeech($text, $company);
    }

    /**
     * @return array{intent: string, confidence: float, method: string, entities?: array<string, mixed>}
     */
    public function classifyIntent(string $message, ?Company $company = null): array
    {
        return $this->intents->classify($message, $company);
    }

    /**
     * @return array<string, mixed>
     */
    public function extractEntities(string $message, ?Company $company = null): array
    {
        return $this->entities->extract($message, $company);
    }

    public function generateImage(string $prompt, ?Company $company = null): GeminiImageResult
    {
        return $this->gateway->generateImage($prompt, $company);
    }

    /**
     * Snapshot of orchestration routing for admin dashboards.
     *
     * @return array<string, mixed>
     */
    public function routingMap(): array
    {
        $useCases = config('ai.use_cases', []);
        $recommended = config('ai.recommended_defaults', []);
        $deterministic = config('ai.deterministic_handlers', []);

        return [
            'useCases' => $useCases,
            'recommendedDefaults' => $recommended,
            'deterministicHandlers' => $deterministic,
            'capabilities' => AiModel::capabilities(),
        ];
    }
}
