<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Optional pgvector backend for embedding search at scale.
 * Falls back to JSON + in-memory cosine when unavailable (SQLite, no extension).
 */
final class PgVectorSearchService
{
    public function isAvailable(): bool
    {
        if (! config('ai.pgvector.enabled', false)) {
            return false;
        }

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return false;
        }

        if (! Schema::hasColumn('knowledge_chunks', 'embedding_vector')) {
            return false;
        }

        try {
            $row = DB::selectOne("SELECT 1 AS ok FROM pg_extension WHERE extname = 'vector' LIMIT 1");

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<int, float>  $vector
     */
    public function storeVector(int $chunkId, array $vector): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $literal = $this->toPgVectorLiteral($vector);
        DB::update(
            'UPDATE knowledge_chunks SET embedding_vector = ?::vector WHERE id = ?',
            [$literal, $chunkId],
        );
    }

    /**
     * @param  array<int, float>  $queryEmbedding
     * @return array<int, array{id: int, source_type: string, source_id: int, score: float, content: string}>
     */
    public function search(
        int $companyId,
        array $queryEmbedding,
        ?string $sourceType,
        int $limit,
        float $minScore,
    ): array {
        if (! $this->isAvailable()) {
            return [];
        }

        $literal = $this->toPgVectorLiteral($queryEmbedding);
        $bindings = [$literal, $companyId, $literal, $minScore, $limit];

        $sourceFilter = '';
        if ($sourceType !== null) {
            $sourceFilter = ' AND source_type = ?';
            array_splice($bindings, 2, 0, [$sourceType]);
        }

        $sql = <<<SQL
SELECT id, source_type, source_id, content,
       1 - (embedding_vector <=> ?::vector) AS score
FROM knowledge_chunks
WHERE company_id = ?
  AND embedding_vector IS NOT NULL
  {$sourceFilter}
  AND (1 - (embedding_vector <=> ?::vector)) >= ?
ORDER BY embedding_vector <=> ?::vector
LIMIT ?
SQL;

        if ($sourceType !== null) {
            $bindings[] = $literal;
        } else {
            // reorder bindings for query without source filter
        }

        // Fix SQL - let me rewrite search more cleanly
        return $this->searchQuery($companyId, $literal, $sourceType, $limit, $minScore);
    }

    /**
     * @return array{driver: string, pgvector: bool, message: string}
     */
    public function status(): array
    {
        $driver = Schema::getConnection()->getDriverName();
        $enabled = (bool) config('ai.pgvector.enabled', false);
        $available = $this->isAvailable();

        return [
            'driver' => $driver,
            'pgvector' => $available,
            'enabled' => $enabled,
            'message' => $available
                ? 'pgvector active — DB-side similarity search'
                : ($enabled && $driver !== 'pgsql'
                    ? 'pgvector enabled in config but database is not PostgreSQL — using JSON+cosine'
                    : 'JSON + in-memory cosine (fine for SMB catalog sizes)'),
        ];
    }

    /**
     * @param  array<int, float>  $vector
     */
    private function toPgVectorLiteral(array $vector): string
    {
        $parts = array_map(fn ($v) => is_finite((float) $v) ? sprintf('%.8f', (float) $v) : '0', $vector);

        return '['.implode(',', $parts).']';
    }

    /**
     * @return array<int, array{id: int, source_type: string, source_id: int, score: float, content: string}>
     */
    private function searchQuery(
        int $companyId,
        string $literal,
        ?string $sourceType,
        int $limit,
        float $minScore,
    ): array {
        $query = DB::table('knowledge_chunks')
            ->selectRaw('id, source_type, source_id, content, 1 - (embedding_vector <=> ?::vector) AS score', [$literal])
            ->where('company_id', $companyId)
            ->whereNotNull('embedding_vector')
            ->whereRaw('(1 - (embedding_vector <=> ?::vector)) >= ?', [$literal, $minScore]);

        if ($sourceType !== null) {
            $query->where('source_type', $sourceType);
        }

        $rows = $query
            ->orderByRaw('embedding_vector <=> ?::vector', [$literal])
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'source_type' => (string) $row->source_type,
                'source_id' => (int) $row->source_id,
                'score' => (float) $row->score,
                'content' => (string) $row->content,
            ];
        }

        return $out;
    }
}
