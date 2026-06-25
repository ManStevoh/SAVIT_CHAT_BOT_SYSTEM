<?php

namespace App\Services\AI;

/**
 * Redacts common PII before storing conversation learning samples (GDPR / data minimization).
 */
final class LearningPiiRedactor
{
    public function redact(string $text): string
    {
        $out = $text;

        $out = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[email]', $out) ?? $out;
        $out = preg_replace('/\b(?:\+?\d{1,3}[-.\s]?)?\(?\d{2,4}\)?[-.\s]?\d{3,4}[-.\s]?\d{3,4}\b/', '[phone]', $out) ?? $out;
        $out = preg_replace('/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', '[card]', $out) ?? $out;
        $out = preg_replace('/\b\d{10,16}\b/', '[number]', $out) ?? $out;

        return trim(preg_replace('/\s+/', ' ', $out) ?? $out);
    }
}
