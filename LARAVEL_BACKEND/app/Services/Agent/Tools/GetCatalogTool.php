<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\OrderFlowService;

final class GetCatalogTool implements AgentTool
{
    public function __construct(
        protected OrderFlowService $orderFlow,
    ) {}

    public function name(): string
    {
        return 'get_catalog';
    }

    public function description(): string
    {
        return 'Get the full numbered product catalog list. Do NOT call this tool when the customer is selecting a product number (e.g. 1, 2) or ordering an item — call process_order_message instead.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $this->orderFlow->initializeProductStep($context->chat, $context->company);

        return [
            'catalog' => $this->orderFlow->formatCatalogForDisplay($context->company),
        ];
    }
}
