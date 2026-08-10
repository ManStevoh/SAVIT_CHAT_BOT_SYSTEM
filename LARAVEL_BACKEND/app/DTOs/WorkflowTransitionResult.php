<?php

namespace App\DTOs;

final readonly class WorkflowTransitionResult
{
    /**
     * @param array<int, array{type: string, payload: array<string, mixed>}> $executedActions
     */
    public function __construct(
        public ConversationState $nextState,
        public array $executedActions = [],
        public ?string $responseSpec = null,
        public ?string $customerReply = null,
        public ?string $payUrl = null,
        public array $extra = [],
    ) {}
}
