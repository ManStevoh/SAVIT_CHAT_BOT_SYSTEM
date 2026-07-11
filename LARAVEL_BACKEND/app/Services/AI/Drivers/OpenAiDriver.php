<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\ResolvedAiModel;
use App\Services\AI\EmbedResult;
use App\Services\AI\OpenAiChatResult;

class OpenAiDriver extends AbstractAiDriver
{
    public function chatCompletion(
        ResolvedAiModel $resolved,
        array $messages,
        int $maxTokens,
        ?float $temperature,
        bool $jsonMode,
        int $timeoutSeconds,
    ): \App\Services\AI\OpenAiChatResult {
        $base = rtrim($resolved->apiBaseUrl ?: $resolved->provider->api_base_url ?: 'https://api.openai.com/v1', '/');
        $started = microtime(true);

        $payload = [
            'model' => $resolved->model->model_key,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];
        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }
        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = $this->postWithRetry("{$base}/chat/completions", $resolved->apiKey, $payload, $timeoutSeconds);
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
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        return $this->successResult(
            $resolved,
            (string) ($data['choices'][0]['message']['content'] ?? ''),
            (int) ($usage['prompt_tokens'] ?? 0),
            (int) ($usage['completion_tokens'] ?? 0),
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
        $base = rtrim($resolved->apiBaseUrl ?: $resolved->provider->api_base_url ?: 'https://api.openai.com/v1', '/');
        $started = microtime(true);

        $payload = [
            'model' => $resolved->model->model_key,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'tools' => $tools,
            'tool_choice' => 'auto',
        ];
        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        try {
            $response = $this->postWithRetry("{$base}/chat/completions", $resolved->apiKey, $payload, $timeoutSeconds);
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
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $message = is_array($data['choices'][0]['message'] ?? null) ? $data['choices'][0]['message'] : [];
        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $tc) {
            if (! is_array($tc)) {
                continue;
            }
            $fn = is_array($tc['function'] ?? null) ? $tc['function'] : [];
            $toolCalls[] = [
                'id' => (string) ($tc['id'] ?? ''),
                'name' => (string) ($fn['name'] ?? ''),
                'arguments' => (string) ($fn['arguments'] ?? '{}'),
            ];
        }

        $content = isset($message['content']) ? (string) $message['content'] : null;

        return new OpenAiChatResult(
            content: $content !== '' ? $content : null,
            success: true,
            model: $resolved->model->model_key,
            promptTokens: (int) ($usage['prompt_tokens'] ?? 0),
            completionTokens: (int) ($usage['completion_tokens'] ?? 0),
            totalTokens: (int) ($usage['total_tokens'] ?? 0),
            latencyMs: $this->elapsed($started),
            httpStatus: $response->status(),
            providerId: $resolved->provider->id,
            modelId: $resolved->model->id,
            toolCalls: $toolCalls,
            finishReason: (string) ($data['choices'][0]['finish_reason'] ?? ''),
        );
    }

    public function embed(ResolvedAiModel $resolved, string $text, int $timeoutSeconds = 30): ?EmbedResult
    {
        $base = rtrim($resolved->apiBaseUrl ?: $resolved->provider->api_base_url ?: 'https://api.openai.com/v1', '/');

        try {
            $response = $this->postWithRetry("{$base}/embeddings", $resolved->apiKey, [
                'model' => $resolved->model->model_key,
                'input' => $text,
            ], $timeoutSeconds);
        } catch (\Throwable) {
            return null;
        }

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $vector = $response->json('data.0.embedding');
        if (! is_array($vector)) {
            return null;
        }

        $usage = is_array($response->json('usage')) ? $response->json('usage') : [];

        return new EmbedResult(
            vector: array_map('floatval', $vector),
            promptTokens: (int) ($usage['prompt_tokens'] ?? 0),
            totalTokens: (int) ($usage['total_tokens'] ?? 0),
        );
    }

    private function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
