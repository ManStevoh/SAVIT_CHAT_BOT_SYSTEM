<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\AiRequestLog;
use App\Models\Company;
use App\Models\PlatformSetting;
use App\Services\AI\Drivers\OpenAiDriver;
use App\Services\AI\Drivers\Contracts\AiProviderDriver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Multi-provider AI gateway (OpenAI, Anthropic, Google, OpenAI-compatible APIs).
 */
class AiGateway
{
    public const USE_CASE_IMAGE_GENERATION = 'image_generation';

    public function __construct(
        protected AiModelResolver $resolver,
        protected AiDriverFactory $driverFactory,
        protected AiBillingService $billing,
        protected GeminiImageService $geminiImage,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chatCompletion(
        array $messages,
        string $useCase,
        ?Company $company = null,
        ?int $chatId = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        int $timeoutSeconds = 30,
        bool $jsonMode = false,
    ): OpenAiChatResult {
        $resolved = $this->resolver->resolve($company, AiModel::CAPABILITY_CHAT, $useCase);
        if ($resolved === null) {
            $result = new OpenAiChatResult(
                content: null,
                success: false,
                model: 'unknown',
                error: 'No AI provider or model configured',
            );
            $this->persistLog($result, $useCase, $company?->id, $chatId);

            return $result;
        }

        if ($company && $resolved->credentialSource === 'platform' && ! $this->billing->isWithinPlatformAiBudget($company)) {
            $result = new OpenAiChatResult(
                content: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'AI usage limit reached for your plan. Add your own API key in Settings or upgrade.',
                httpStatus: 402,
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
            $this->persistLog($result, $useCase, $company->id, $chatId, $resolved);

            return $result;
        }

        if (! $this->consumeRateLimit($company?->id)) {
            $result = new OpenAiChatResult(
                content: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'AI rate limit exceeded for this minute',
                httpStatus: 429,
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
            $this->persistLog($result, $useCase, $company?->id, $chatId, $resolved);

            return $result;
        }

        $maxTokens ??= (int) ($resolved->model->max_output_tokens ?: 512);

        $driver = $this->driverFactory->driverFor($resolved->provider);
        $result = $driver->chatCompletion($resolved, $messages, $maxTokens, $temperature, $jsonMode, $timeoutSeconds);
        $result = $this->withCost($resolved->model, $result);
        $this->persistLog($result, $useCase, $company?->id, $chatId, $resolved);

        return $result;
    }

    /**
     * @return array<int, float>|null
     */
    public function embedText(string $text, ?int $companyId = null): ?array
    {
        $company = $companyId ? Company::find($companyId) : null;
        $resolved = $this->resolver->resolve($company, AiModel::CAPABILITY_EMBEDDING, AiUseCase::EMBEDDING);
        if ($resolved === null || ! $this->consumeRateLimit($company?->id)) {
            return null;
        }

        $driver = $this->driverFactory->driverFor($resolved->provider);
        $started = microtime(true);
        $embed = $driver->embed($resolved, $text);

        if ($embed === null) {
            $result = new OpenAiChatResult(
                content: null,
                success: false,
                model: $resolved->model->model_key,
                latencyMs: (int) round((microtime(true) - $started) * 1000),
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
                error: 'Embedding failed',
            );
            $this->persistLog($result, OpenAiClient::USE_CASE_EMBEDDING, $companyId, null, $resolved);

            return null;
        }

        $result = new OpenAiChatResult(
            content: null,
            success: true,
            model: $resolved->model->model_key,
            promptTokens: $embed->promptTokens,
            completionTokens: 0,
            totalTokens: $embed->totalTokens,
            latencyMs: (int) round((microtime(true) - $started) * 1000),
            providerId: $resolved->provider->id,
            modelId: $resolved->model->id,
            estimatedCostUsd: $resolved->model->estimateCostUsd($embed->promptTokens, 0),
        );
        $this->persistLog($result, OpenAiClient::USE_CASE_EMBEDDING, $companyId, null, $resolved);

        return $embed->vector;
    }

    public function generateImage(string $prompt, ?Company $company = null): GeminiImageResult
    {
        $resolved = $this->resolver->resolve($company, AiModel::CAPABILITY_IMAGE, AiUseCase::IMAGE);
        if ($resolved === null) {
            $result = new GeminiImageResult(
                imageBytes: null,
                mimeType: null,
                success: false,
                model: config('gemini.image_model', 'gemini-2.5-flash-image'),
                error: 'No Gemini image model configured',
            );
            $this->persistImageLog($result, $company?->id);

            return $result;
        }

        if ($company && $resolved->credentialSource === 'platform' && ! $this->billing->isWithinPlatformAiBudget($company)) {
            $result = new GeminiImageResult(
                imageBytes: null,
                mimeType: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'AI usage limit reached for your plan. Add your own API key in Settings or upgrade.',
                httpStatus: 402,
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
            $this->persistImageLog($result, $company->id, $resolved);

            return $result;
        }

        if (! $this->consumeRateLimit($company?->id)) {
            $result = new GeminiImageResult(
                imageBytes: null,
                mimeType: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'AI rate limit exceeded for this minute',
                httpStatus: 429,
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
            $this->persistImageLog($result, $company?->id, $resolved);

            return $result;
        }

        $result = $this->geminiImage->generate($prompt, $company);
        $this->persistImageLog($result, $company?->id, $resolved);

        return $result;
    }

    public function transcribeAudio(string $filePath, string $filename, Company $company): TranscribeResult
    {
        $resolved = $this->resolver->resolve($company, AiModel::CAPABILITY_STT, AiUseCase::SPEECH_TO_TEXT);
        if ($resolved === null) {
            return new TranscribeResult(
                text: null,
                success: false,
                model: 'unknown',
                error: 'No speech-to-text model configured',
            );
        }

        if ($resolved->credentialSource === 'platform' && ! $this->billing->isWithinPlatformAiBudget($company)) {
            return new TranscribeResult(
                text: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'AI usage limit reached for your plan.',
                httpStatus: 402,
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        if (! $this->consumeRateLimit($company->id)) {
            return new TranscribeResult(
                text: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'AI rate limit exceeded',
                httpStatus: 429,
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        $driver = $this->driverFactory->driverFor($resolved->provider);
        if (! $driver instanceof OpenAiDriver) {
            return new TranscribeResult(
                text: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'Speech-to-text requires an OpenAI-compatible provider.',
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        $started = microtime(true);
        $result = $driver->transcribe($resolved, $filePath, $filename);
        $latencyMs = (int) round((microtime(true) - $started) * 1000);

        $transcribe = new TranscribeResult(
            text: $result?->text,
            success: $result !== null && $result->success,
            model: $resolved->model->model_key,
            latencyMs: $latencyMs,
            httpStatus: $result?->httpStatus,
            error: $result?->error,
            providerId: $resolved->provider->id,
            modelId: $resolved->model->id,
        );

        $logResult = new OpenAiChatResult(
            content: $transcribe->text,
            success: $transcribe->success,
            model: $transcribe->model,
            latencyMs: $latencyMs,
            httpStatus: $transcribe->httpStatus,
            error: $transcribe->error,
            providerId: $transcribe->providerId,
            modelId: $transcribe->modelId,
        );
        $this->persistLog($logResult, AiUseCase::SPEECH_TO_TEXT, $company->id, null, $resolved);

        return $transcribe;
    }

    public function synthesizeSpeech(string $text, Company $company, ?string $voice = null): SynthesizeResult
    {
        $resolved = $this->resolver->resolve($company, AiModel::CAPABILITY_TTS, AiUseCase::TEXT_TO_SPEECH);
        if ($resolved === null) {
            return new SynthesizeResult(
                audioPath: null,
                mimeType: null,
                success: false,
                model: 'unknown',
                error: 'No text-to-speech model configured',
            );
        }

        if ($resolved->credentialSource === 'platform' && ! $this->billing->isWithinPlatformAiBudget($company)) {
            return new SynthesizeResult(
                audioPath: null,
                mimeType: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'AI usage limit reached for your plan.',
                httpStatus: 402,
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        if (! $this->consumeRateLimit($company->id)) {
            return new SynthesizeResult(
                audioPath: null,
                mimeType: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'AI rate limit exceeded',
                httpStatus: 429,
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        $driver = $this->driverFactory->driverFor($resolved->provider);
        if (! $driver instanceof OpenAiDriver) {
            return new SynthesizeResult(
                audioPath: null,
                mimeType: null,
                success: false,
                model: $resolved->model->model_key,
                error: 'TTS requires an OpenAI-compatible provider.',
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        $started = microtime(true);
        $voiceName = $voice ?? config('agent.voice.tts_voice', 'alloy');
        $format = config('agent.voice.tts_format', 'mp3');
        $result = $driver->synthesize($resolved, $text, $voiceName, $format);

        $logResult = new OpenAiChatResult(
            content: null,
            success: $result->success,
            model: $result->model,
            latencyMs: (int) round((microtime(true) - $started) * 1000),
            httpStatus: $result->httpStatus,
            error: $result->error,
            providerId: $result->providerId,
            modelId: $result->modelId,
        );
        $this->persistLog($logResult, AiUseCase::TEXT_TO_SPEECH, $company->id, null, $resolved);

        return $result;
    }

    protected function withCost(AiModel $model, OpenAiChatResult $result): OpenAiChatResult
    {
        $cost = $model->estimateCostUsd($result->promptTokens, $result->completionTokens);

        return new OpenAiChatResult(
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
    }

    protected function persistLog(
        OpenAiChatResult $result,
        string $useCase,
        ?int $companyId,
        ?int $chatId = null,
        ?ResolvedAiModel $resolved = null,
    ): void {
        $credentialSource = $resolved?->credentialSource;
        $selectionSource = $resolved?->selectionSource;
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
                'selection_source' => $selectionSource,
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
        } catch (\Throwable $e) {
            Log::warning('Failed to persist AI request log', ['error' => $e->getMessage()]);
        }
    }

    protected function persistImageLog(
        GeminiImageResult $result,
        ?int $companyId,
        ?ResolvedAiModel $resolved = null,
    ): void {
        $credentialSource = $resolved?->credentialSource;
        $billed = $credentialSource !== null
            ? $this->billing->billedCostUsd($result->estimatedCostUsd ?? 0.0, $credentialSource)
            : null;

        try {
            AiRequestLog::create([
                'company_id' => $companyId,
                'ai_provider_id' => $result->providerId,
                'ai_model_id' => $result->modelId,
                'use_case' => self::USE_CASE_IMAGE_GENERATION,
                'credential_source' => $credentialSource,
                'selection_source' => $resolved?->selectionSource,
                'model' => $result->model,
                'prompt_tokens' => $result->promptTokens,
                'completion_tokens' => $result->completionTokens,
                'total_tokens' => $result->promptTokens + $result->completionTokens,
                'estimated_cost_usd' => $result->estimatedCostUsd,
                'billed_cost_usd' => $billed,
                'latency_ms' => $result->latencyMs,
                'success' => $result->success,
                'http_status' => $result->httpStatus,
                'error_message' => $result->error ? mb_substr($result->error, 0, 500) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist AI image log', ['error' => $e->getMessage()]);
        }
    }

    protected function consumeRateLimit(?int $companyId = null): bool
    {
        $platform = PlatformSetting::first();
        $limit = $platform?->rate_limit_per_minute;
        if (! $limit || $limit <= 0) {
            return true;
        }

        $key = $companyId
            ? 'ai-gateway:company:'.$companyId.':'.now()->format('YmdHi')
            : 'ai-gateway:'.now()->format('YmdHi');
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return false;
        }

        RateLimiter::hit($key, 120);

        return true;
    }
}
