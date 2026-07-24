<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\ResolvedAiModel;
use App\Services\AI\EmbedResult;
use App\Services\AI\OpenAiChatResult;
use App\Services\AI\SynthesizeResult;
use App\Services\AI\TranscribeResult;

class OpenAiDriver extends AbstractAiDriver implements \App\Services\AI\Drivers\Contracts\SupportsToolCalling
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

    public function transcribe(
        ResolvedAiModel $resolved,
        string $filePath,
        string $filename,
        int $timeoutSeconds = 60,
    ): TranscribeResult {
        $base = rtrim($resolved->apiBaseUrl ?: $resolved->provider->api_base_url ?: 'https://api.openai.com/v1', '/');
        $started = microtime(true);

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($resolved->apiKey)
                ->timeout($timeoutSeconds)
                ->attach('file', file_get_contents($filePath), $filename)
                ->post("{$base}/audio/transcriptions", [
                    'model' => $resolved->model->model_key,
                    'response_format' => 'text',
                ]);
        } catch (\Throwable $e) {
            return new TranscribeResult(
                text: null,
                success: false,
                model: $resolved->model->model_key,
                latencyMs: $this->elapsed($started),
                error: $e->getMessage(),
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        if (! $response->successful()) {
            return new TranscribeResult(
                text: null,
                success: false,
                model: $resolved->model->model_key,
                latencyMs: $this->elapsed($started),
                httpStatus: $response->status(),
                error: $this->errorMessage($response),
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        $text = trim($response->body());

        return new TranscribeResult(
            text: $text !== '' ? $text : null,
            success: $text !== '',
            model: $resolved->model->model_key,
            latencyMs: $this->elapsed($started),
            httpStatus: $response->status(),
            providerId: $resolved->provider->id,
            modelId: $resolved->model->id,
        );
    }

    public function synthesize(
        ResolvedAiModel $resolved,
        string $text,
        string $voice = 'alloy',
        string $format = 'mp3',
        int $timeoutSeconds = 60,
    ): SynthesizeResult {
        $base = rtrim($resolved->apiBaseUrl ?: $resolved->provider->api_base_url ?: 'https://api.openai.com/v1', '/');
        $started = microtime(true);

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($resolved->apiKey)
                ->timeout($timeoutSeconds)
                ->post("{$base}/audio/speech", [
                    'model' => $resolved->model->model_key,
                    'input' => mb_substr($text, 0, 4096),
                    'voice' => $voice,
                    'response_format' => $format,
                ]);
        } catch (\Throwable $e) {
            return new SynthesizeResult(
                audioPath: null,
                mimeType: null,
                success: false,
                model: $resolved->model->model_key,
                latencyMs: $this->elapsed($started),
                error: $e->getMessage(),
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        if (! $response->successful()) {
            return new SynthesizeResult(
                audioPath: null,
                mimeType: null,
                success: false,
                model: $resolved->model->model_key,
                latencyMs: $this->elapsed($started),
                httpStatus: $response->status(),
                error: $this->errorMessage($response),
                providerId: $resolved->provider->id,
                modelId: $resolved->model->id,
            );
        }

        $mime = $format === 'opus' ? 'audio/ogg' : 'audio/mpeg';
        $ext = $format === 'opus' ? 'ogg' : 'mp3';
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tts_'.uniqid('', true).'.'.$ext;
        file_put_contents($path, $response->body());

        return new SynthesizeResult(
            audioPath: $path,
            mimeType: $mime,
            success: true,
            model: $resolved->model->model_key,
            latencyMs: $this->elapsed($started),
            httpStatus: $response->status(),
            providerId: $resolved->provider->id,
            modelId: $resolved->model->id,
        );
    }

    private function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
