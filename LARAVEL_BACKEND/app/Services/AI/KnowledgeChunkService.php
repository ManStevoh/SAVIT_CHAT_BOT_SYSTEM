<?php

namespace App\Services\AI;

use App\Models\Faq;
use App\Models\KnowledgeChunk;
use App\Models\Product;

final class KnowledgeChunkService
{
    private const CHUNK_CHARS = 600;

    private const OVERLAP_CHARS = 80;

    public function __construct(
        protected OpenAiClient $openAiClient,
        protected AiLearningConfig $learningConfig,
    ) {}

    /**
     * @return array<int, string>
     */
    public function splitText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) <= self::CHUNK_CHARS) {
            return [$text];
        }

        $chunks = [];
        $offset = 0;
        $len = mb_strlen($text);
        while ($offset < $len) {
            $piece = mb_substr($text, $offset, self::CHUNK_CHARS);
            $chunks[] = trim($piece);
            if ($offset + self::CHUNK_CHARS >= $len) {
                break;
            }
            $offset += self::CHUNK_CHARS - self::OVERLAP_CHARS;
        }

        return array_values(array_filter($chunks));
    }

    public function syncFaq(Faq $faq): void
    {
        if (! $this->learningConfig->faqEmbeddingsEnabled()) {
            return;
        }

        KnowledgeChunk::query()
            ->where('company_id', $faq->company_id)
            ->where('source_type', KnowledgeChunk::SOURCE_FAQ)
            ->where('source_id', $faq->id)
            ->delete();

        $payload = "Question: {$faq->question}\nAnswer: {$faq->answer}";
        foreach ($this->splitText($payload) as $index => $chunkText) {
            $embedding = $this->openAiClient->embedText($chunkText, (int) $faq->company_id);
            KnowledgeChunk::create([
                'company_id' => $faq->company_id,
                'source_type' => KnowledgeChunk::SOURCE_FAQ,
                'source_id' => $faq->id,
                'chunk_index' => $index,
                'content' => $chunkText,
                'embedding' => $embedding,
            ]);
        }
    }

    public function syncProduct(Product $product): void
    {
        if (! $this->learningConfig->learningEmbeddingsEnabled()) {
            return;
        }

        $product->loadMissing(['variants', 'images']);

        KnowledgeChunk::query()
            ->where('company_id', $product->company_id)
            ->where('source_type', KnowledgeChunk::SOURCE_PRODUCT)
            ->where('source_id', $product->id)
            ->delete();

        $lines = ["Product: {$product->name}"];
        if ($product->description) {
            $lines[] = "Description: {$product->description}";
        }
        if ($product->price) {
            $lines[] = "Price: {$product->price}";
        }
        foreach ($product->variants as $variant) {
            $attrs = is_array($variant->attributes) ? implode(', ', $variant->attributes) : '';
            $lines[] = "Variant {$variant->label}: {$variant->price}".($attrs !== '' ? " ({$attrs})" : '');
        }

        $fullText = implode("\n", $lines);
        $primaryEmbedding = null;

        foreach ($this->splitText($fullText) as $index => $chunkText) {
            $embedding = $this->openAiClient->embedText($chunkText, (int) $product->company_id);
            if ($index === 0) {
                $primaryEmbedding = $embedding;
            }
            KnowledgeChunk::create([
                'company_id' => $product->company_id,
                'source_type' => KnowledgeChunk::SOURCE_PRODUCT,
                'source_id' => $product->id,
                'chunk_index' => $index,
                'content' => $chunkText,
                'embedding' => $embedding,
            ]);
        }

        if ($primaryEmbedding !== null) {
            $product->update(['catalog_embedding' => $primaryEmbedding]);
        }
    }

    /**
     * @return array<int, array{source_type: string, source_id: int, score: float, content: string}>
     */
    public function search(int $companyId, string $query, ?string $sourceType = null, int $limit = 5): array
    {
        $queryEmbedding = $this->openAiClient->embedText(trim($query), $companyId);
        if ($queryEmbedding === null) {
            return [];
        }

        $chunksQuery = KnowledgeChunk::query()
            ->where('company_id', $companyId)
            ->whereNotNull('embedding');

        if ($sourceType !== null) {
            $chunksQuery->where('source_type', $sourceType);
        }

        $chunks = $chunksQuery->get();
        $candidates = VectorCandidateFilter::topLexical(
            $chunks->all(),
            $query,
            fn (KnowledgeChunk $c) => $c->content,
            60,
        );

        $minScore = $sourceType === KnowledgeChunk::SOURCE_FAQ
            ? $this->learningConfig->faqSemanticMinScore()
            : $this->learningConfig->learningSemanticMinScore();

        $scored = [];
        foreach ($candidates as $chunk) {
            if (! is_array($chunk->embedding)) {
                continue;
            }
            $score = VectorSimilarity::cosine($queryEmbedding, $chunk->embedding);
            if ($score >= $minScore) {
                $scored[] = [
                    'source_type' => $chunk->source_type,
                    'source_id' => (int) $chunk->source_id,
                    'score' => $score,
                    'content' => $chunk->content,
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $seen = [];
        $out = [];
        foreach ($scored as $row) {
            $key = $row['source_type'].':'.$row['source_id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
