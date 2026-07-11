<?php

namespace App\Services\Agent;

use App\Models\Chat;
use App\Models\Company;

/**
 * Immutable execution context scoped to one customer turn.
 */
final readonly class AgentToolContext
{
    public function __construct(
        public Company $company,
        public Chat $chat,
        public string $customerPhone,
        public ?string $customerName,
        public string $incomingMessage,
    ) {}
}
