<?php

namespace App\Services\Conversation;

/**
 * Adds lightweight hints for OpenAI when the latest message is ambiguous alone
 * (e.g. "ok", "thanks") so replies stay human and context-aware.
 */
class CustomerMessageClassifier
{
    private const STOPWORDS = [
        'a', 'an', 'the', 'to', 'of', 'in', 'on', 'for', 'is', 'are', 'was', 'were',
        'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
        'would', 'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need',
        'i', 'you', 'we', 'they', 'he', 'she', 'it', 'me', 'my', 'your', 'our', 'their',
        'and', 'or', 'but', 'if', 'that', 'this', 'these', 'those', 'with', 'from',
        'at', 'by', 'as', 'not', 'no', 'yes', 'so', 'than', 'then', 'just', 'also',
    ];

    /**
     * Optional hint block appended for the model (WhatsApp plain text; no markdown).
     */
    public function buildOpenAiHint(string $message): ?string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return null;
        }

        $lower = mb_strtolower($trimmed);
        $words = $this->significantWords($lower);

        if (count($words) >= 3) {
            return null;
        }

        if ($this->looksLikeAcknowledgment($lower)) {
            return 'The customer sent a very short acknowledgment (thanks / ok / yes type). Reply in one or two short, warm sentences in plain text. Do not repeat the full product catalog or menu unless they ask. If they were in the middle of ordering, encourage them to continue with product numbers when ready.';
        }

        if (mb_strlen($trimmed) <= 2 && preg_match('/^[a-z]$/i', $trimmed)) {
            return 'The customer sent a very short message (single letter). Ask politely what they need, in one short sentence.';
        }

        return null;
    }

    private function looksLikeAcknowledgment(string $lower): bool
    {
        $ack = [
            'ok', 'okay', 'k', 'kk', 'thanks', 'thank you', 'thx', 'ty', 'cheers',
            'yes', 'yep', 'yeah', 'no', 'nope', 'sure', 'alright', 'got it', 'nice',
            'great', 'cool', 'perfect', 'shukran', 'merci',
        ];
        foreach ($ack as $a) {
            if ($lower === $a || str_starts_with($lower, $a.' ') || str_starts_with($lower, $a.',') || str_starts_with($lower, $a.'!')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function significantWords(string $lower): array
    {
        $tokens = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
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
