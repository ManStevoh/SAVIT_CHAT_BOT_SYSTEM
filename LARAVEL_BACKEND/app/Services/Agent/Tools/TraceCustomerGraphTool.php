<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Company\CommerceKnowledgeGraphService;
use App\Services\Agent\Company\CustomerIntentChainService;
use App\Services\Agent\Contracts\AgentTool;

/**
 * Traverse customer → orders → products → related catalog (knowledge graph).
 */
final class TraceCustomerGraphTool implements AgentTool
{
    public function __construct(
        protected CommerceKnowledgeGraphService $graph,
        protected CustomerIntentChainService $intentChains,
    ) {}

    public function name(): string
    {
        return 'trace_customer_graph';
    }

    public function description(): string
    {
        return 'Traverse the customer knowledge graph: past orders, products bought, and related in-stock items. Use for "batteries for what I bought", reorder, compatibility questions.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Optional related product search e.g. battery, ink, accessory'],
            ],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        $result = $this->graph->traceCustomer(
            (int) $context->company->id,
            $context->customerPhone,
            $query !== '' ? $query : null,
            $context->company->settings?->displayCurrencyCode() ?? 'USD',
        );

        $reorder = $this->intentChains->reorderSignal((int) $context->company->id, $context->customerPhone);
        if ($reorder !== null) {
            $result['reorder_prediction'] = $reorder;
        }

        return $result;
    }
}
