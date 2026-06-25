<?php

namespace App\Services\AI;

final class GeminiImageResult
{
    public function __construct(
        public readonly ?string $imageBytes,
        public readonly ?string $mimeType,
        public readonly bool $success,
        public readonly string $model,
        public readonly ?string $error = null,
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly int $latencyMs = 0,
        public readonly ?int $httpStatus = null,
        public readonly ?int $providerId = null,
        public readonly ?int $modelId = null,
        public readonly ?float $estimatedCostUsd = null,
    ) {}
}
