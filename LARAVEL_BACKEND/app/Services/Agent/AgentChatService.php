<?php

namespace App\Services\Agent;

use App\Models\AiModel;
use App\Models\AiRequestLog;
use App\Models\Company;
use App\Services\AI\AiBillingService;
use App\Services\AI\AiDriverFactory;
use App\Services\AI\AiModelResolver;
use App\Services\AI\AiUseCase;
use App\Services\AI\Drivers\Contracts\SupportsToolCalling;
use App\Services\AI\Drivers\OpenAiDriver;
use App\Services\AI\OpenAiChatResult;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Tool-capable chat completions (OpenAI, Anthropic, Gemini).
 */
class AgentChatService
{
    public function __construct(
        protected AiModelResolver $resolver,
        protected AiDriverFactory $driverFactory,
        protected AiBillingService $billing,
    ) {}

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
        string $useCase = AiUseCase::AGENT_COMMERCE,
    ): OpenAiChatResult {
        $resolved = $this->resolver->resolve($company, AiModel::CAPABILITY_REASONING, $useCase);
        if ($resolved === null) {
            $resolved = $this->resolver->resolve($company, AiModel::CAPABILITY_CHAT, $useCase);
        }
        if ($resolved === null) {
            return new OpenAiChatResult(
                content: null,
                success: false,
                model: 'unknown',
                error: 'No AI provider configured',
            );
        }

        if ($resolved->credentialSource === 'platform' && ! $this->billing->isWithinPlatformAiBudget($company)) {
            return new OpenAiChatResult(
                content: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'AI usage limit reached for your plan.',
                httpStatus: 402,
            );
        }

        if (! $this->consumeRateLimit($company->id)) {
            return new OpenAiChatResult(
                content: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'AI rate limit exceeded',
                httpStatus: 429,
            );
        }

        $driver = $this->driverFactory->driverFor($resolved->provider);
        if (! $driver instanceof SupportsToolCalling) {
            return new OpenAiChatResult(
                content: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'Agent tool mode requires a provider that supports function/tool calling (OpenAI-compatible, Anthropic, or Gemini).',
            );
        }

        $maxTokens ??= (int) ($resolved->model->max_output_tokens ?: 800);

        $result = $driver->chatCompletionWithTools(
            $resolved,
            $messages,
            $tools,
            $maxTokens,
            $temperature,
            $timeoutSeconds,
        );

        $cost = $resolved->model->estimateCostUsd($result->promptTokens, $result->completionTokens);
        $result = new OpenAiChatResult(
            content: $result->content,
            success: $result->success,
            model: $result->model,
            promptTokens: $result->promptTokens,
            completionTokens: $result->completionTokens,
            totalTokens: $result->totalTokens,
            latencyMs: $result->latencyMs,
            httpStatus: $result->httpStatus,
            error: $result->error,
            providerId: $result->providerId,
            modelId: $result->modelId,
            estimatedCostUsd: $cost,
            toolCalls: $result->toolCalls,
            finishReason: $result->finishReason,
        );

        $this->persistLog($result, $company->id, $chatId, $resolved->credentialSource, $useCase);

        return $result;
    }

    /**
     * Vision analysis — single image + instruction (no tools).
     */
    public function completeWithVision(
        Company $company,
        string $imageUrl,
        string $instruction,
        ?int $chatId = null,
        bool $jsonMode = true,
        int $timeoutSeconds = 40,
    ): OpenAiChatResult {
        $resolved = $this->resolver->resolve($company, AiModel::CAPABILITY_VISION, AiUseCase::AGENT_VISION);
        if ($resolved === null) {
            $resolved = $this->resolver->resolve($company, AiModel::CAPABILITY_CHAT, AiUseCase::AGENT_VISION);
        }
        if ($resolved === null) {
            return new OpenAiChatResult(
                content: null,
                success: false,
                model: 'unknown',
                error: 'No AI provider configured',
            );
        }

        if ($resolved->credentialSource === 'platform' && ! $this->billing->isWithinPlatformAiBudget($company)) {
            return new OpenAiChatResult(
                content: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'AI usage limit reached for your plan.',
                httpStatus: 402,
            );
        }

        if (! $this->consumeRateLimit($company->id)) {
            return new OpenAiChatResult(
                content: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'AI rate limit exceeded',
                httpStatus: 429,
            );
        }

        $driver = $this->driverFactory->driverFor($resolved->provider);
        if (! $driver instanceof OpenAiDriver) {
            return new OpenAiChatResult(
                content: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'Vision requires an OpenAI-compatible chat provider.',
            );
        }

        $messages = [[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $instruction],
                ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
            ],
        ]];

        $maxTokens = min(600, (int) ($resolved->model->max_output_tokens ?: 600));

        $result = $driver->chatCompletion(
            $resolved,
            $messages,
            $maxTokens,
            0.2,
            $jsonMode,
            $timeoutSeconds,
        );

        $cost = $resolved->model->estimateCostUsd($result->promptTokens, $result->completionTokens);
        $result = new OpenAiChatResult(
            content: $result->content,
            success: $result->success,
            model: $result->model,
            promptTokens: $result->promptTokens,
            completionTokens: $result->completionTokens,
            totalTokens: $result->totalTokens,
            latencyMs: $result->latencyMs,
            httpStatus: $result->httpStatus,
            error: $result->error,
            providerId: $result->providerId,
            modelId: $result->modelId,
            estimatedCostUsd: $cost,
        );

        $this->persistLog($result, $company->id, $chatId, $resolved->credentialSource, AiUseCase::AGENT_VISION);

        return $result;
    }

    protected function persistLog(
        OpenAiChatResult $result,
        int $companyId,
        ?int $chatId,
        ?string $credentialSource,
        string $useCase = AiUseCase::AGENT_COMMERCE,
    ): void {
        $billed = $credentialSource !== null
            ? $this->billing->billedCostUsd($result->estimatedCostUsd, $credentialSource)
            : null;

        try {
            AiRequestLog::create([
                'company_id' => $companyId,
                'ai_provider_id' => $result->providerId,
                'ai_model_id' => $result->modelId,
                'chat_id' => $chatId,
                'use_case' => $useCase,
                'credential_source' => $credentialSource,
                'model' => $result->model,
                'prompt_tokens' => $result->promptTokens,
                'completion_tokens' => $result->completionTokens,
                'total_tokens' => $result->totalTokens,
                'estimated_cost_usd' => $result->estimatedCostUsd,
                'billed_cost_usd' => $billed,
                'latency_ms' => $result->latencyMs,
                'success' => $result->success,
                'http_status' => $result->httpStatus,
                'error_message' => $result->error ? mb_substr($result->error, 0, 500) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // non-fatal
        }
    }

    protected function consumeRateLimit(int $companyId): bool
    {
        $key = 'ai-agent:company:'.$companyId.':'.now()->format('YmdHi');
        $limit = (int) config('agent.rate_limit_per_minute', 60);
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return false;
        }
        RateLimiter::hit($key, 120);

        return true;
    }
}
