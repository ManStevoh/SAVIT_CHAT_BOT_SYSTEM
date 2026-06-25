<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Drivers\Contracts\AiProviderDriver;
use App\Services\AI\OpenAiChatResult;
use App\Services\AI\ResolvedAiModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class AbstractAiDriver implements AiProviderDriver
{
  protected function postWithRetry(
        string $url,
        string $apiKey,
        array $payload,
        int $timeoutSeconds,
        array $headers = [],
    ): ?Response {
        $last = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::withToken($apiKey)
                    ->withHeaders($headers)
                    ->timeout($timeoutSeconds)
                    ->post($url, $payload);
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
            $last = $response;
        }

        return $last;
    }

    protected function shouldRetry(Response $response): bool
    {
        $status = $response->status();

        return $status === 429 || $status >= 500;
    }

    protected function errorMessage(Response $response): string
    {
        $message = $response->json('error.message') ?? $response->json('message');

        return is_string($message) && $message !== ''
            ? $message
            : 'HTTP '.$response->status();
    }

    protected function failedResult(ResolvedAiModel $resolved, int $latencyMs, ?int $status, string $error): OpenAiChatResult
    {
        return new OpenAiChatResult(
            content: null,
            success: false,
            model: $resolved->model->model_key,
            latencyMs: $latencyMs,
            httpStatus: $status,
            error: $error,
            providerId: $resolved->provider->id,
            modelId: $resolved->model->id,
        );
    }

    protected function successResult(
        ResolvedAiModel $resolved,
        string $content,
        int $promptTokens,
        int $completionTokens,
        int $latencyMs,
        ?int $status,
    ): OpenAiChatResult {
        return new OpenAiChatResult(
            content: trim($content) !== '' ? trim($content) : null,
            success: trim($content) !== '',
            model: $resolved->model->model_key,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $promptTokens + $completionTokens,
            latencyMs: $latencyMs,
            httpStatus: $status,
            providerId: $resolved->provider->id,
            modelId: $resolved->model->id,
        );
    }
}
