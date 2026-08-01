<?php

namespace App\Support;

/**
 * Shared input sanitization for user messages across all AI paths.
 * Strips control characters and enforces length limits while preserving Unicode.
 */
final class MessageSanitizer
{
    public static function sanitize(string $message, int $maxLength = 4000): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message) ?? '';

        return mb_substr(trim($clean), 0, $maxLength);
    }
}
