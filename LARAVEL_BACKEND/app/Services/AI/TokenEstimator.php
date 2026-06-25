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

        return $len === 0 ? 0 : (int) max(1, ceil($len / 4));
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
