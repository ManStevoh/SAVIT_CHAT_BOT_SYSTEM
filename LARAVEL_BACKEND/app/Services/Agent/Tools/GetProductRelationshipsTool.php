<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Company\ProductGraphService;
use App\Services\Agent\Contracts\AgentTool;

final class GetProductRelationshipsTool implements AgentTool
{
    public function __construct(protected ProductGraphService $graph) {}

    public function name(): string
    {
        return 'get_product_relationships';
    }

    public function description(): string
    {
        return 'Get product knowledge graph edges: accessories, warranties, bundles, complements, replacements.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product_id' => ['type' => 'integer', 'description' => 'Product ID'],
                'product_name' => ['type' => 'string', 'description' => 'Search by name if ID unknown'],
            ],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $companyId = (int) $context->company->id;
        $currency = $context->company->settings?->displayCurrencyCode() ?? 'USD';
        $productId = (int) ($arguments['product_id'] ?? 0);

        if ($productId <= 0) {
            $name = trim((string) ($arguments['product_name'] ?? ''));
            if ($name === '') {
                return ['found' => false, 'message' => 'Provide product_id or product_name.'];
            }
            $matches = $this->graph->searchByName($companyId, $name, 1);
            if ($matches === []) {
                return ['found' => false, 'message' => 'No product matched that name.'];
            }
            $productId = (int) $matches[0]['id'];
        }

        return $this->graph->graphForProduct($companyId, $productId, $currency);
    }
}
