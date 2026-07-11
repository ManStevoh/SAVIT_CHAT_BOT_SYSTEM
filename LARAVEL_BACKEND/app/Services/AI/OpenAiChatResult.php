<?php

namespace App\Services\AI;

readonly class OpenAiChatResult
{
    public function __construct(
        public ?string $content,
        public bool $success,
        public string $model,
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public int $totalTokens = 0,
        public int $latencyMs = 0,
        public ?int $httpStatus = null,
        public ?string $error = null,
        public ?int $providerId = null,
        public ?int $modelId = null,
        public float $estimatedCostUsd = 0.0,
        /** @var array<int, array{id: string, name: string, arguments: string}> */
        public array $toolCalls = [],
        public ?string $finishReason = null,
    ) {}
}
