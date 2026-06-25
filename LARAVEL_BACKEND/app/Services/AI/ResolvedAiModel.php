<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Company;

readonly class ResolvedAiModel
{
    public function __construct(
        public AiProvider $provider,
        public AiModel $model,
        public string $selectionSource,
        public string $apiKey,
        public string $credentialSource = 'platform',
        public ?string $apiBaseUrl = null,
    ) {}
}
