<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\Company;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Nano Banana — Gemini native image generation via generateContent.
 */
final class GeminiImageService
{
    public function __construct(
        private AiModelResolver $resolver,
    ) {}

    public function generate(
        string $prompt,
        ?Company $company = null,
        ?string $modelKey = null,
        ?int $timeoutSeconds = null,
    ): GeminiImageResult {
        $resolved = $this->resolver->resolve($company, AiModel::CAPABILITY_IMAGE);
        if ($resolved === null) {
            return new GeminiImageResult(
                imageBytes: null,
                mimeType: null,
                success: false,
                model: $modelKey ?? config('gemini.image_model', 'gemini-2.5-flash-image'),
                error: 'No Gemini image model configured. Set GEMINI_API_KEY or add a Google API key in Admin → AI providers.',
            );
        }

        $model = $modelKey ?? $resolved->model->model_key;
        $timeoutSeconds ??= (int) config('gemini.image_timeout_seconds', 90);
        $base = rtrim($resolved->apiBaseUrl ?: $resolved->provider->api_base_url ?: config('gemini.api_base_url'), '/');
        $url = "{$base}/models/{$model}:generateContent?key=".urlencode($resolved->apiKey);

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
            ],
        ];

        $started = microtime(true);

        try {
            $response = $this->postJson($url, $payload, $timeoutSeconds);
        } catch (\Throwable $e) {
            return new GeminiImageResult(
                imageBytes: null,
                mimeType: null,
                success: false,
                model: $model,
                error: $e->getMessage(),
                latencyMs: $this->elapsed($started),
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        if ($response === null || ! $response->successful()) {
            return new GeminiImageResult(
                imageBytes: null,
                mimeType: null,
                success: false,
                model: $model,
                error: $response ? $this->errorMessage($response) : 'Request failed',
                latencyMs: $this->elapsed($started),
                httpStatus: $response?->status(),
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        $data = $response->json();
        $imageBytes = null;
        $mimeType = null;

        foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inline)) {
                continue;
            }
            $b64 = $inline['data'] ?? null;
            if (! is_string($b64) || $b64 === '') {
                continue;
            }
            $decoded = base64_decode($b64, true);
            if ($decoded === false) {
                continue;
            }
            $imageBytes = $decoded;
            $mimeType = $inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png';
            break;
        }

        if ($imageBytes === null) {
            return new GeminiImageResult(
                imageBytes: null,
                mimeType: null,
                success: false,
                model: $model,
                error: 'Gemini returned no image data',
                latencyMs: $this->elapsed($started),
                httpStatus: $response->status(),
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        $usage = $data['usageMetadata'] ?? [];
        $promptTokens = (int) ($usage['promptTokenCount'] ?? 0);
        $completionTokens = (int) ($usage['candidatesTokenCount'] ?? 0);
        $cost = $resolved->model->estimateCostUsd($promptTokens, $completionTokens);

        return new GeminiImageResult(
            imageBytes: $imageBytes,
            mimeType: $mimeType,
            success: true,
            model: $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            latencyMs: $this->elapsed($started),
            httpStatus: $response->status(),
            providerId: $resolved->provider->id,
            modelId: $resolved->model->id,
            estimatedCostUsd: $cost,
        );
    }

    protected function postJson(string $url, array $payload, int $timeoutSeconds): ?Response
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::timeout($timeoutSeconds)->post($url, $payload);
            } catch (ConnectionException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
                usleep($attempt * 500_000);

                continue;
            }

            if ($response->successful() || ! $this->shouldRetry($response) || $attempt >= 3) {
                return $response;
            }

            usleep($attempt * 500_000);
        }

        return null;
    }

    protected function shouldRetry(Response $response): bool
    {
        return in_array($response->status(), [429, 500, 502, 503, 504], true);
    }

    protected function errorMessage(Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) && $message !== ''
            ? $message
            : 'HTTP '.$response->status();
    }

    private function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
