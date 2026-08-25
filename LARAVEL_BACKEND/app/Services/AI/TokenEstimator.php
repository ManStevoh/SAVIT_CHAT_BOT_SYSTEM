<?php

namespace App\Services\AI;

/**
 * Rough token estimate (~4 characters per token) for prompt budgeting.
 */
class TokenEstimator
{
    public static function estimate(string $text): int
    {
        $len = mb_strlen($text);

        if ($len === 0) {
            return 0;
        }

        // Count ASCII printable characters (single-byte = ~4 chars/token)
        $asciiLen = strlen(preg_replace('/[^\x20-\x7E]/', '', $text) ?? '');
        $nonAsciiLen = $len - $asciiLen;

        // ASCII: ~4 chars/token; non-ASCII: ~2 chars/token (conservative for multi-byte scripts)
        $estimate = ($asciiLen / 4) + ($nonAsciiLen / 2);

        return (int) max(1, ceil($estimate));
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public static function estimateMessages(array $messages): int
    {
        $total = 0;
        foreach ($messages as $message) {
            $total += self::estimate($message['content'] ?? '');
        }

        return $total;
    }
}
