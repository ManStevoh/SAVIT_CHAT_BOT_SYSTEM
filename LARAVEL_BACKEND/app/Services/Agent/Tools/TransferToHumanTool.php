<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;

final class TransferToHumanTool implements AgentTool
{
    public function name(): string
    {
        return 'transfer_to_human';
    }

    public function description(): string
    {
        return 'Escalate to a human teammate only when the customer clearly wants a person, the issue needs human judgment/policy exception, or no available tool can fulfill the request. Never use for routine lookups, invoices, tracking, or orders the tools can handle.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reason' => ['type' => 'string', 'description' => 'Brief reason for handoff'],
            ],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $context->chat->update(['agent_handling_at' => now()]);

        return [
            'handoff' => true,
            'reason' => mb_substr(trim((string) ($arguments['reason'] ?? '')), 0, 500),
            'message' => 'Customer has been transferred to a human agent.',
        ];
    }
}
