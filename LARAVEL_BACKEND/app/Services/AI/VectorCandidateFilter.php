<?php

namespace App\Services\AI;

/**
 * Lexical pre-filter before in-memory cosine scans — keeps retrieval fast at scale.
 */
final class VectorCandidateFilter
{
    private const STOPWORDS = [
        'a', 'an', 'the', 'to', 'of', 'in', 'on', 'for', 'is', 'are', 'was', 'were',
        'i', 'you', 'we', 'they', 'and', 'or', 'what', 'how', 'when', 'where', 'why',
    ];

    /**
     * @param  iterable<mixed>  $items
     * @param  callable(mixed): string  $textExtractor
     * @return array<int, mixed>
     */
    public static function topLexical(iterable $items, string $query, callable $textExtractor, int $limit = 80): array
    {
        $queryWords = self::significantWords(mb_strtolower($query));
        if ($queryWords === []) {
            return is_array($items) ? array_slice($items, 0, $limit) : iterator_to_array($items, false);
        }

        $scored = [];
        foreach ($items as $item) {
            $text = mb_strtolower($textExtractor($item));
            $textWords = self::significantWords($text);
            $intersect = count(array_intersect($queryWords, $textWords));
            $score = $intersect / max(1, count($queryWords));
            if ($score > 0) {
                $scored[] = ['item' => $item, 'score' => $score];
            }
        }

        if ($scored === []) {
            $all = is_array($items) ? $items : iterator_to_array($items, false);

            return array_slice($all, 0, $limit);
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(
            fn ($row) => $row['item'],
            array_slice($scored, 0, $limit),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function significantWords(string $text): array
    {
        $tokens = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($tokens as $t) {
            $t = mb_strtolower($t);
            if (mb_strlen($t) < 2 || in_array($t, self::STOPWORDS, true)) {
                continue;
            }
            $out[] = $t;
        }

        return array_values(array_unique($out));
    }
}
