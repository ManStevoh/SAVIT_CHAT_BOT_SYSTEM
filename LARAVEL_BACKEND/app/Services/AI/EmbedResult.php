<?php

namespace App\Services\AI;

readonly class EmbedResult
{
    /**
     * @param  array<int, float>  $vector
     */
    public function __construct(
        public array $vector,
        public int $promptTokens = 0,
        public int $totalTokens = 0,
    ) {}
}
