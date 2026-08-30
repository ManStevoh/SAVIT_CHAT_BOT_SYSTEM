<?php

namespace App\Services\AI;

use App\Models\PlatformSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Diagnose platform OpenAI credentials without going through the billed AI gateway.
 */
class OpenAiConnectionTester
{
    private const MODELS_URL = 'https://api.openai.com/v1/models';

    private const CHAT_URL = 'https://api.openai.com/v1/chat/completions';

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     failedStep: string|null,
     *     details: array<string, mixed>
     * }
     */
    public function test(?string $apiKey, ?string $model, ?int $maxTokens): array
    {
        $started = microtime(true);
        $resolvedKey = $this->resolveApiKey($apiKey);
        $resolvedModel = $this->resolveModel($model);
        $testMaxTokens = max(8, min($maxTokens ?? 16, 32));

        $checks = [
            $this->check('api_key', 'API key', 'skipped'),
            $this->check('model', 'Model availability', 'skipped'),
            $this->check('completion', 'Generate a test reply', 'skipped'),
        ];

        if ($resolvedKey === null) {
            return $this->result(
                success: false,
                message: 'No OpenAI API key is configured.',
                failedStep: 'missing_key',
                checks: $this->mark($checks, 'api_key', 'failed'),
                model: $resolvedModel,
                started: $started,
                hint: 'Paste a key starting with sk- in the field above, or save one first. Environment fallback OPENAI_API_KEY is also empty.',
            );
        }

        try {
            $modelResponse = Http::withToken($resolvedKey)
                ->acceptJson()
                ->timeout(20)
                ->get(self::MODELS_URL.'/'.rawurlencode($resolvedModel));
        } catch (ConnectionException $e) {
            return $this->networkFailure($checks, $resolvedModel, $started, $e);
        } catch (Throwable $e) {
            return $this->networkFailure($checks, $resolvedModel, $started, $e);
        }

        $authFailure = $this->classifyAuthFailure($modelResponse);
        if ($authFailure !== null) {
            $parsed = $this->parseError($modelResponse);

            return $this->result(
                success: false,
                message: $authFailure['message'],
                failedStep: $authFailure['step'],
                checks: $this->mark($checks, 'api_key', 'failed'),
                model: $resolvedModel,
                started: $started,
                httpStatus: $modelResponse->status(),
                openaiError: $parsed['message'],
                openaiCode: $parsed['code'],
                openaiType: $parsed['type'],
                hint: $authFailure['hint'],
            );
        }

        $checks = $this->mark($checks, 'api_key', 'passed');

        if ($modelResponse->status() === 404) {
            $parsed = $this->parseError($modelResponse);

            return $this->result(
                success: false,
                message: "API key is valid, but model “{$resolvedModel}” is not available to this account.",
                failedStep: 'model',
                checks: $this->mark($checks, 'model', 'failed'),
                model: $resolvedModel,
                started: $started,
                httpStatus: 404,
                openaiError: $parsed['message'],
                openaiCode: $parsed['code'],
                openaiType: $parsed['type'],
                hint: 'Check the model name (for example gpt-4o-mini) or pick a model this project can use in the OpenAI dashboard.',
            );
        }

        if (! $modelResponse->successful()) {
            $parsed = $this->parseError($modelResponse);
            $classified = $this->classifyHttpFailure($modelResponse, $parsed);

            return $this->result(
                success: false,
                message: $classified['message'],
                failedStep: $classified['step'],
                checks: $this->mark($checks, 'model', 'failed'),
                model: $resolvedModel,
                started: $started,
                httpStatus: $modelResponse->status(),
                openaiError: $parsed['message'],
                openaiCode: $parsed['code'],
                openaiType: $parsed['type'],
                hint: $classified['hint'],
            );
        }

        $checks = $this->mark($checks, 'model', 'passed');

        try {
            $chatResponse = $this->sendTestCompletion($resolvedKey, $resolvedModel, $testMaxTokens);
        } catch (ConnectionException $e) {
            return $this->result(
                success: false,
                message: 'API key and model were accepted, but the completion request could not reach OpenAI.',
                failedStep: 'network',
                checks: $this->mark($checks, 'completion', 'failed'),
                model: $resolvedModel,
                started: $started,
                openaiError: $e->getMessage(),
                hint: 'Check outbound HTTPS from this server (firewall, DNS, or proxy).',
            );
        } catch (Throwable $e) {
            return $this->result(
                success: false,
                message: 'API key and model were accepted, but the completion request failed.',
                failedStep: 'completion',
                checks: $this->mark($checks, 'completion', 'failed'),
                model: $resolvedModel,
                started: $started,
                openaiError: $e->getMessage(),
                hint: 'See the error details below.',
            );
        }

        if (! $chatResponse->successful()) {
            $parsed = $this->parseError($chatResponse);
            $classified = $this->classifyHttpFailure($chatResponse, $parsed);

            return $this->result(
                success: false,
                message: 'API key and model were accepted, but OpenAI could not generate a test reply.',
                failedStep: $classified['step'] === 'authentication' ? 'completion' : $classified['step'],
                checks: $this->mark($checks, 'completion', 'failed'),
                model: $resolvedModel,
                started: $started,
                httpStatus: $chatResponse->status(),
                openaiError: $parsed['message'],
                openaiCode: $parsed['code'],
                openaiType: $parsed['type'],
                hint: $classified['hint'],
            );
        }

        $reply = trim((string) ($chatResponse->json('choices.0.message.content') ?? ''));
        $usedModel = (string) ($chatResponse->json('model') ?? $resolvedModel);

        return $this->result(
            success: true,
            message: "Connected. Model {$usedModel} replied successfully.",
            failedStep: null,
            checks: $this->mark($checks, 'completion', 'passed'),
            model: $usedModel,
            started: $started,
            replyPreview: $reply !== '' ? mb_substr($reply, 0, 160) : null,
        );
    }

    private function sendTestCompletion(string $apiKey, string $model, int $maxTokens): Response
    {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Reply with the single word ok.'],
            ],
            'max_tokens' => $maxTokens,
        ];

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(30)
            ->post(self::CHAT_URL, $payload);

        $parsed = $this->parseError($response);
        $needsCompletionTokens = is_string($parsed['message'])
            && str_contains(strtolower($parsed['message']), 'max_completion_tokens');

        if ($response->status() === 400 && $needsCompletionTokens) {
            unset($payload['max_tokens']);
            $payload['max_completion_tokens'] = $maxTokens;

            return Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(30)
                ->post(self::CHAT_URL, $payload);
        }

        return $response;
    }

    private function resolveApiKey(?string $provided): ?string
    {
        if (is_string($provided) && $provided !== '' && $provided !== '********') {
            return $provided;
        }

        $saved = PlatformSetting::query()->value('openai_api_key');
        if (is_string($saved) && $saved !== '') {
            return $saved;
        }

        $fallback = config('openai.api_key');

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    private function resolveModel(?string $provided): string
    {
        if (is_string($provided) && trim($provided) !== '') {
            return trim($provided);
        }

        $saved = PlatformSetting::query()->value('openai_model');
        if (is_string($saved) && trim($saved) !== '') {
            return trim($saved);
        }

        $fallback = config('openai.model', 'gpt-4o-mini');

        return is_string($fallback) && $fallback !== '' ? $fallback : 'gpt-4o-mini';
    }

    /**
     * @return array{step: string, message: string, hint: string}|null
     */
    private function classifyAuthFailure(Response $response): ?array
    {
        return match ($response->status()) {
            401 => [
                'step' => 'authentication',
                'message' => 'OpenAI rejected this API key.',
                'hint' => 'Check the key is complete, not revoked, and belongs to an active OpenAI project.',
            ],
            403 => [
                'step' => 'permission',
                'message' => 'This API key does not have permission to use the Models API.',
                'hint' => 'Check organization or project permissions in the OpenAI dashboard.',
            ],
            default => null,
        };
    }

    /**
     * @param  array{message: ?string, code: ?string, type: ?string}  $parsed
     * @return array{step: string, message: string, hint: string}
     */
    private function classifyHttpFailure(Response $response, array $parsed): array
    {
        $code = $parsed['code'] ?? '';
        $status = $response->status();

        if ($status === 429 && $code === 'insufficient_quota') {
            return [
                'step' => 'quota',
                'message' => 'OpenAI quota or billing limit was reached.',
                'hint' => 'Add billing or wait for the quota to reset in the OpenAI dashboard.',
            ];
        }

        if ($status === 429) {
            return [
                'step' => 'rate_limit',
                'message' => 'OpenAI rate-limited this request.',
                'hint' => 'Wait a moment and try again.',
            ];
        }

        if ($status === 404) {
            return [
                'step' => 'model',
                'message' => 'OpenAI could not find this model for the API key.',
                'hint' => 'Check the model name or pick one this project can use.',
            ];
        }

        if ($status >= 500) {
            return [
                'step' => 'network',
                'message' => 'OpenAI returned a server error.',
                'hint' => 'This is usually temporary. Try again in a minute.',
            ];
        }

        return [
            'step' => 'completion',
            'message' => $parsed['message'] ?: ('OpenAI returned HTTP '.$status),
            'hint' => 'See the OpenAI error below for the exact reason.',
        ];
    }

    /**
     * @return array{message: ?string, code: ?string, type: ?string}
     */
    private function parseError(Response $response): array
    {
        $json = $response->json();
        $error = is_array($json['error'] ?? null) ? $json['error'] : [];

        $message = $error['message'] ?? ($json['message'] ?? null);
        $code = $error['code'] ?? ($json['code'] ?? null);
        $type = $error['type'] ?? ($json['type'] ?? null);

        return [
            'message' => is_string($message) && $message !== '' ? $message : null,
            'code' => is_string($code) && $code !== '' ? $code : null,
            'type' => is_string($type) && $type !== '' ? $type : null,
        ];
    }

    /**
     * @param  array<int, array{id: string, label: string, status: string}>  $checks
     * @return array<int, array{id: string, label: string, status: string}>
     */
    private function mark(array $checks, string $id, string $status): array
    {
        return array_map(
            static fn (array $check): array => $check['id'] === $id ? [...$check, 'status' => $status] : $check,
            $checks
        );
    }

    /**
     * @return array{id: string, label: string, status: string}
     */
    private function check(string $id, string $label, string $status): array
    {
        return ['id' => $id, 'label' => $label, 'status' => $status];
    }

    /**
     * @param  array<int, array{id: string, label: string, status: string}>  $checks
     */
    private function networkFailure(array $checks, string $model, float $started, Throwable $e): array
    {
        $timeout = str_contains(strtolower($e->getMessage()), 'timed out');

        return $this->result(
            success: false,
            message: $timeout
                ? 'OpenAI did not respond in time.'
                : 'Could not reach api.openai.com.',
            failedStep: $timeout ? 'timeout' : 'network',
            checks: $this->mark($checks, 'api_key', 'failed'),
            model: $model,
            started: $started,
            openaiError: $e->getMessage(),
            hint: 'Check outbound HTTPS from this server (firewall, DNS, or proxy).',
        );
    }

    /**
     * @param  array<int, array{id: string, label: string, status: string}>  $checks
     * @return array{
     *     success: bool,
     *     message: string,
     *     failedStep: string|null,
     *     details: array<string, mixed>
     * }
     */
    private function result(
        bool $success,
        string $message,
        ?string $failedStep,
        array $checks,
        string $model,
        float $started,
        ?int $httpStatus = null,
        ?string $openaiError = null,
        ?string $openaiCode = null,
        ?string $openaiType = null,
        ?string $hint = null,
        ?string $replyPreview = null,
    ): array {
        return [
            'success' => $success,
            'message' => $message,
            'failedStep' => $failedStep,
            'details' => [
                'httpStatus' => $httpStatus,
                'openaiError' => $openaiError,
                'openaiCode' => $openaiCode,
                'openaiType' => $openaiType,
                'model' => $model,
                'latencyMs' => (int) round((microtime(true) - $started) * 1000),
                'hint' => $hint,
                'replyPreview' => $replyPreview,
                'checks' => $checks,
            ],
        ];
    }
}
