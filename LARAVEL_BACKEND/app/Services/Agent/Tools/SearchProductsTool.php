<?php

namespace App\Services\Agent\Tools;

use App\Models\KnowledgeChunk;
use App\Models\Product;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\AI\KnowledgeChunkService;
use App\Support\MoneyFormatter;

final class SearchProductsTool implements AgentTool
{
    public function __construct(
        protected KnowledgeChunkService $knowledgeChunks,
    ) {}

    public function name(): string
    {
        return 'search_products';
    }

    public function description(): string
    {
        return 'Search the business product catalog by query. Returns names, prices, stock, and descriptions.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Search terms, product name, or category'],
                'limit' => ['type' => 'integer', 'description' => 'Max results (1-10)', 'minimum' => 1, 'maximum' => 10],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        $limit = max(1, min(10, (int) ($arguments['limit'] ?? 5)));
        if ($query === '') {
            return ['products' => [], 'message' => 'Query is required.'];
        }

        $companyId = (int) $context->company->id;
        $currency = $context->company->settings?->displayCurrencyCode() ?? 'USD';

        $semantic = $this->knowledgeChunks->search($companyId, $query, KnowledgeChunk::SOURCE_PRODUCT, $limit);
        $productIds = array_values(array_unique(array_map(fn ($r) => (int) $r['source_id'], $semantic)));

        $lexical = Product::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', '%'.$query.'%')
                    ->orWhere('description', 'like', '%'.$query.'%');
            })
            ->limit($limit)
            ->pluck('id')
            ->all();

        $ids = array_values(array_unique(array_merge($productIds, $lexical)));
        if ($ids === []) {
            return ['products' => [], 'message' => 'No products matched.'];
        }

        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', array_slice($ids, 0, $limit))
            ->get(['id', 'name', 'price', 'stock', 'description']);

        return [
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => MoneyFormatter::format((float) $p->price, $currency),
                'stock' => $p->stock,
                'description' => mb_substr((string) ($p->description ?? ''), 0, 200),
            ])->values()->all(),
        ];
    }
}
