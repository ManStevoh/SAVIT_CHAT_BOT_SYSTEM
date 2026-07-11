<?php

namespace App\Services\Agent\Specialists\Contracts;

use App\Models\Chat;
use App\Models\Company;

interface CommerceSpecialist
{
    public function type(): string;

    /**
     * @param  array<string, mixed>  $perception
     * @return array{perspective: string, confidence: float, source: string}
     */
    public function consultForTurn(
        Company $company,
        Chat $chat,
        string $incomingMessage,
        array $perception,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function analyzeBackground(Company $company, array $input = []): array;
}
