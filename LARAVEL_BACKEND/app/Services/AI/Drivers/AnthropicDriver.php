<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\AnthropicToolPayloadConverter;
use App\Services\AI\Drivers\Contracts\SupportsToolCalling;
use App\Services\AI\EmbedResult;
use App\Services\AI\OpenAiChatResult;
use App\Services\AI\ResolvedAiModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AnthropicDriver extends AbstractAiDriver implements SupportsToolCalling
{
    public function chatCompletion(
        ResolvedAiModel $resolved,
        array $messages,
        int $maxTokens,
        ?float $temperature,
        bool $jsonMode,
        int $timeoutSeconds,
    ): \App\Services\AI\OpenAiChatResult {
        $base = rtrim($resolved->apiBaseUrl ?: $resolved->provider->api_base_url ?: 'https://api.anthropic.com/v1', '/');
        $started = microtime(true);

        $system = '';
        $anthropicMessages = [];
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system .= ($system !== '' ? "\n\n" : '').$message['content'];

                continue;
            }
            $anthropicMessages[] = [
                'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $message['content'],
            ];
        }

        if ($jsonMode) {
            $system .= ($system !== '' ? "\n\n" : '').'Respond with valid JSON only.';
        }

        $payload = [
            'model' => $resolved->model->model_key,
            'max_tokens' => $maxTokens,
            'messages' => $anthropicMessages,
        ];
        if ($system !== '') {
            $payload['system'] = $system;
        }
        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        $headers = [
            'x-api-key' => $resolved->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];

        try {
            $response = $this->postJson("{$base}/messages", $payload, $timeoutSeconds, $headers);
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
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= $block['text'] ?? '';
            }
        }
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        return $this->successResult(
            $resolved,
            $content,
            (int) ($usage['input_tokens'] ?? 0),
            (int) ($usage['output_tokens'] ?? 0),
            $this->elapsed($started),
            $response->status(),
        );
    }

    public function embed(ResolvedAiModel $resolved, string $text, int $timeoutSeconds = 30): ?EmbedResult
    {
        return null;
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
        $base = rtrim($resolved->apiBaseUrl ?: $resolved->provider->api_base_url ?: 'https://api.anthropic.com/v1', '/');
        $started = microtime(true);
        $converter = new AnthropicToolPayloadConverter;
        $converted = $converter->messages($messages);
        $anthropicTools = $converter->tools($tools);

        $payload = [
            'model' => $resolved->model->model_key,
            'max_tokens' => $maxTokens,
            'messages' => $converted['messages'],
            'tools' => $anthropicTools,
        ];
        if ($converted['system'] !== '') {
            $payload['system'] = $converted['system'];
        }
        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        $headers = [
            'x-api-key' => $resolved->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];

        try {
            $response = $this->postJson("{$base}/messages", $payload, $timeoutSeconds, $headers);
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
        $parsed = $converter->parseResponseContent(is_array($data['content'] ?? null) ? $data['content'] : []);
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        return new OpenAiChatResult(
            content: $parsed['content'],
            success: true,
            model: $resolved->model->model_key,
            promptTokens: (int) ($usage['input_tokens'] ?? 0),
            completionTokens: (int) ($usage['output_tokens'] ?? 0),
            totalTokens: (int) (($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0)),
            latencyMs: $this->elapsed($started),
            httpStatus: $response->status(),
            providerId: $resolved->provider->id,
            modelId: $resolved->model->id,
            toolCalls: $parsed['toolCalls'],
            finishReason: (string) ($data['stop_reason'] ?? ''),
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function postJson(string $url, array $payload, int $timeoutSeconds, array $headers): ?Response
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::withHeaders($headers)->timeout($timeoutSeconds)->post($url, $payload);
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
