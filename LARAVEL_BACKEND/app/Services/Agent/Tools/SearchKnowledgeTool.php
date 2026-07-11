<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\AI\KnowledgeChunkService;

final class SearchKnowledgeTool implements AgentTool
{
    public function __construct(
        protected KnowledgeChunkService $knowledgeChunks,
    ) {}

    public function name(): string
    {
        return 'search_knowledge';
    }

    public function description(): string
    {
        return 'Semantic search across business knowledge (products, FAQs, catalog text).';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 8],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        $limit = max(1, min(8, (int) ($arguments['limit'] ?? 5)));
        if ($query === '') {
            return ['chunks' => []];
        }

        $results = $this->knowledgeChunks->search((int) $context->company->id, $query, null, $limit);

        return [
            'chunks' => array_map(fn ($r) => [
                'type' => $r['source_type'],
                'source_id' => $r['source_id'],
                'score' => round($r['score'], 3),
                'content' => mb_substr($r['content'], 0, 400),
            ], $results),
        ];
    }
}
