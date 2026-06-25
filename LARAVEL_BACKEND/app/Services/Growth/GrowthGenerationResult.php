<?php

namespace App\Services\Growth;

readonly class GrowthGenerationResult
{
    /**
     * @param  array<int, array<string, mixed>>  $posts
     */
    public function __construct(
        public array $posts,
        public bool $aiGenerated,
        public ?string $aiError = null,
    ) {}
}
