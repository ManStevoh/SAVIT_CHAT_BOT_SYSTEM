<?php

namespace App\Services\AI;

final class TranscribeResult
{
    public function __construct(
        public readonly ?string $text,
        public readonly bool $success,
        public readonly string $model,
        public readonly int $latencyMs = 0,
        public readonly ?int $httpStatus = null,
        public readonly ?string $error = null,
        public readonly ?int $providerId = null,
        public readonly ?int $modelId = null,
    ) {}
}
