<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Drivers\Contracts\SupportsToolCalling;
use App\Services\AI\EmbedResult;
use App\Services\AI\GeminiToolPayloadConverter;
use App\Services\AI\OpenAiChatResult;
use App\Services\AI\ResolvedAiModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GoogleGeminiDriver extends AbstractAiDriver implements SupportsToolCalling
{
    public function chatCompletion(
        ResolvedAiModel $resolved,
        array $messages,
        int $maxTokens,
        ?float $temperature,
        bool $jsonMode,
        int $timeoutSeconds,
    ): \App\Services\AI\OpenAiChatResult {
        $base = rtrim($resolved->apiBaseUrl ?: $resolved->provider->api_base_url ?: 'https://generativelanguage.googleapis.com/v1beta', '/');
        $model = $resolved->model->model_key;
        $started = microtime(true);
        $apiKey = $resolved->apiKey;

        $system = '';
        $contents = [];
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system .= ($system !== '' ? "\n\n" : '').$message['content'];

                continue;
            }
            $contents[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ];
        }

        $payload = ['contents' => $contents];
        if ($system !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }
        $generationConfig = ['maxOutputTokens' => $maxTokens];
        if ($temperature !== null) {
            $generationConfig['temperature'] = $temperature;
        }
        if ($jsonMode) {
            $generationConfig['responseMimeType'] = 'application/json';
        }
        $payload['generationConfig'] = $generationConfig;

        $url = "{$base}/models/{$model}:generateContent?key=".urlencode($apiKey);

        try {
            $response = $this->postJson($url, $payload, $timeoutSeconds);
        } catch (\Throwable $e) {
            return $this->failedResult($resolved, $this->elapsed($started), null, $e->getMessage());
        }

        if ($response === null || ! $response->successful()) {
            return $this->failedResult(
                $resolved,
                $this->elapsed($started),
                $response?->status(),
                $response ? $this->errorMessage($response) : 'Request failed',
            );
        }

        $data = $response->json();
        $content = '';
        foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
            $content .= $part['text'] ?? '';
        }
        $usage = $data['usageMetadata'] ?? [];

        return $this->successResult(
            $resolved,
            $content,
            (int) ($usage['promptTokenCount'] ?? 0),
            (int) ($usage['candidatesTokenCount'] ?? 0),
            $this->elapsed($started),
            $response->status(),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function chatCompletionWithTools(
        ResolvedAiModel $resolved,
        array $messages,
        array $tools,
        int $maxTokens,
        ?float $temperature,
        int $timeoutSeconds,
    ): OpenAiChatResult {
        $base = rtrim($resolved->apiBaseUrl ?: $resolved->provider->api_base_url ?: 'https://generativelanguage.googleapis.com/v1beta', '/');
        $model = $resolved->model->model_key;
        $started = microtime(true);
        $apiKey = $resolved->apiKey;
        $converter = new GeminiToolPayloadConverter;
        $converted = $converter->contents($messages);

        $payload = [
            'contents' => $converted['contents'],
            'tools' => [$converter->toolConfig($tools)],
        ];
        if ($converted['system'] !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $converted['system']]]];
        }
        $generationConfig = ['maxOutputTokens' => $maxTokens];
        if ($temperature !== null) {
            $generationConfig['temperature'] = $temperature;
        }
        $payload['generationConfig'] = $generationConfig;

        $url = "{$base}/models/{$model}:generateContent?key=".urlencode($apiKey);

        try {
            $response = $this->postJson($url, $payload, $timeoutSeconds);
        } catch (\Throwable $e) {
            return $this->failedResult($resolved, $this->elapsed($started), null, $e->getMessage());
        }

        if ($response === null || ! $response->successful()) {
            return $this->failedResult(
                $resolved,
                $this->elapsed($started),
                $response?->status(),
                $response ? $this->errorMessage($response) : 'Request failed',
            );
        }

        $data = $response->json();
        $parts = is_array($data['candidates'][0]['content']['parts'] ?? null)
            ? $data['candidates'][0]['content']['parts']
            : [];
        $parsed = $converter->parseParts($parts);
        $usage = $data['usageMetadata'] ?? [];

        return new OpenAiChatResult(
            content: $parsed['content'],
            success: true,
            model: $resolved->model->model_key,
            promptTokens: (int) ($usage['promptTokenCount'] ?? 0),
            completionTokens: (int) ($usage['candidatesTokenCount'] ?? 0),
            totalTokens: (int) ($usage['totalTokenCount'] ?? 0),
            latencyMs: $this->elapsed($started),
            httpStatus: $response->status(),
            providerId: $resolved->provider->id,
            modelId: $resolved->model->id,
            toolCalls: $parsed['toolCalls'],
            finishReason: (string) ($data['candidates'][0]['finishReason'] ?? ''),
        );
    }

    public function embed(ResolvedAiModel $resolved, string $text, int $timeoutSeconds = 30): ?EmbedResult
    {
        $base = rtrim($resolved->apiBaseUrl ?: $resolved->provider->api_base_url ?: 'https://generativelanguage.googleapis.com/v1beta', '/');
        $model = $resolved->model->model_key;
        $apiKey = $resolved->apiKey;
        $url = "{$base}/models/{$model}:embedContent?key=".urlencode($apiKey);

        try {
            $response = $this->postJson($url, [
                'model' => "models/{$model}",
                'content' => ['parts' => [['text' => $text]]],
            ], $timeoutSeconds);
        } catch (\Throwable) {
            return null;
        }

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $values = $response->json('embedding.values');
        if (! is_array($values)) {
            return null;
        }

        return new EmbedResult(
            vector: array_map('floatval', $values),
            promptTokens: (int) ($response->json('usageMetadata.promptTokenCount') ?? 0),
            totalTokens: (int) ($response->json('usageMetadata.totalTokenCount') ?? 0),
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

    private function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
