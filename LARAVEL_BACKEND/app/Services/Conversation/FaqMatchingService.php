<?php

namespace App\Services\Conversation;

use App\Models\Company;
use App\Models\Faq;

/**
 * Scored FAQ matching: only auto-reply when confidence is high; otherwise OpenAI
 * answers using the same FAQs in the system prompt.
 */
class FaqMatchingService
{
    private const STOPWORDS = [
        'a', 'an', 'the', 'to', 'of', 'in', 'on', 'for', 'is', 'are', 'was', 'were',
        'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
        'would', 'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need',
        'i', 'you', 'we', 'they', 'he', 'she', 'it', 'me', 'my', 'your', 'our', 'their',
        'and', 'or', 'but', 'if', 'that', 'this', 'these', 'those', 'with', 'from',
        'at', 'by', 'as', 'not', 'no', 'yes', 'so', 'than', 'then', 'just', 'also',
        'what', 'which', 'who', 'whom', 'whose', 'where', 'when', 'why', 'how',
    ];

    /**
     * @return array{answer: string, faq_id: int, score: float}|null
     */
    public function matchBest(Company $company, string $message, string $lower): ?array
    {
        $minScore = (float) config('conversation.faq_direct_answer_min_score', 72);
        $minSub = (int) config('conversation.faq_min_substring_length', 8);

        $faqs = Faq::where('company_id', $company->id)->where('is_active', true)->get();
        if ($faqs->isEmpty()) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($faqs as $faq) {
            $score = $this->scoreFaq($faq, $message, $lower, $minSub);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $faq;
            }
        }

        if ($best === null || $bestScore < $minScore) {
            return null;
        }

        $best->increment('usage_count');

        return [
            'answer' => $best->answer,
            'faq_id' => (int) $best->id,
            'score' => round($bestScore, 2),
        ];
    }

    /**
     * Exposed for unit tests (lexical score 0–100).
     */
    public function scoreFaq(Faq $faq, string $message, string $lower, int $minSubstringLength = 8): float
    {
        $question = mb_strtolower($faq->question);

        $keywords = $faq->keywords;
        if (is_array($keywords)) {
            foreach ($keywords as $kw) {
                $k = mb_strtolower(trim((string) $kw));
                if ($k === '') {
                    continue;
                }
                if (str_contains($lower, $k)) {
                    $len = mb_strlen($k);
                    if ($len >= 4) {
                        return 95.0;
                    }
                    if ($len >= 2) {
                        return 88.0;
                    }
                }
            }
        }

        if (mb_strlen($question) >= $minSubstringLength && str_contains($lower, $question)) {
            return 100.0;
        }

        if (mb_strlen($lower) >= $minSubstringLength && str_contains($question, $lower)) {
            return 90.0;
        }

        $qWords = $this->significantWords($question);
        $mWords = $this->significantWords($lower);
        if ($qWords === [] || $mWords === []) {
            return 0.0;
        }

        $intersect = array_intersect($mWords, $qWords);
        $nInter = count($intersect);
        if ($nInter === 0) {
            return 0.0;
        }

        $qCount = count($qWords);
        $recall = $nInter / max(1, $qCount);
        $union = count(array_unique(array_merge($mWords, $qWords)));
        $jaccard = $union > 0 ? $nInter / $union : 0.0;

        $score = ($recall * 70.0) + ($jaccard * 30.0);

        if ($nInter >= 2 && $recall >= 0.4) {
            $score = max($score, 68.0);
        }

        return min(100.0, $score);
    }

    /**
     * @return array<int, string>
     */
    private function significantWords(string $text): array
    {
        $tokens = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($tokens as $t) {
            $t = mb_strtolower($t);
            if (mb_strlen($t) < 2) {
                continue;
            }
            if (in_array($t, self::STOPWORDS, true)) {
                continue;
            }
            $out[] = $t;
        }

        return array_values(array_unique($out));
    }
}
