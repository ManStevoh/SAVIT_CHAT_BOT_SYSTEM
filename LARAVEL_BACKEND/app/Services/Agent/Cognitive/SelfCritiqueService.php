<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Company;
use App\Models\Product;

/**
 * Self-critique — lightweight review before sending to customer.
 * Catches empathy gaps, refund clarity, length, and internal-leak phrases.
 */
final class SelfCritiqueService
{
    /**
     * @param  array<string, mixed>  $cognitiveContext
     * @return array{passed: bool, issues: list<string>, rewritten: ?string}
     */
    public function review(Company $company, string $draft, array $cognitiveContext): array
    {
        $issues = [];
        $rewritten = $draft;

        $perception = $cognitiveContext['perception'] ?? [];
        $emotion = $perception['emotion'] ?? 'neutral';
        $topic = $perception['topic'] ?? 'general';
        $lower = mb_strtolower($draft);

        if (in_array($emotion, ['disappointed', 'angry', 'angry'], true)) {
            $hasEmpathy = str_contains($lower, 'sorry') || str_contains($lower, 'apolog')
                || str_contains($lower, 'understand') || str_contains($lower, 'hear you')
                || str_contains($lower, 'frustrating');
            if (! $hasEmpathy) {
                $issues[] = 'missing_empathy_for_upset_customer';
                $rewritten = "I'm sorry about that. ".$rewritten;
            }
        }

        if ($topic === 'refund' || ($perception['risk'] ?? '') === 'possible return') {
            if (! str_contains($lower, 'policy') && ! str_contains($lower, 'help')
                && ! str_contains($lower, 'resolve') && ! str_contains($lower, 'refund')) {
                $issues[] = 'refund_topic_needs_clear_resolution_path';
                if (! str_contains(mb_strtolower($rewritten), 'help')) {
                    $rewritten .= ' I can help with our refund policy if you share your order number.';
                }
            }
        }

        if (preg_match('/\b(as an ai|language model|i don\'t have feelings)\b/iu', $draft)) {
            $issues[] = 'leaked_ai_identity';
            $rewritten = preg_replace('/\b(as an ai|language model|i don\'t have feelings)[^.!?]*[.!?]?/iu', '', $rewritten) ?? $rewritten;
            $rewritten = trim(preg_replace('/\s+/', ' ', $rewritten) ?? $rewritten);
        }

        $maxChars = (int) config('agent.max_output_chars', 1800);
        if (mb_strlen($draft) > $maxChars) {
            $issues[] = 'reply_too_long';
            $rewritten = mb_substr($rewritten, 0, $maxChars - 3).'…';
        }

        $forbidden = [
            'internal reasoning',
            'specialist council',
            'confidence:',
            'perception (internal',
            'tool_call',
            'system prompt',
        ];
        foreach ($forbidden as $phrase) {
            if (str_contains(mb_strtolower($draft), $phrase)) {
                $issues[] = 'leaked_internal_reasoning';
                $rewritten = 'Happy to help — could you share a bit more about what you need?';
                break;
            }
        }

        // Catch system-prompt text leaked verbatim into the reply
        if (preg_match('/\byou are (?:a |an |the )?\w*\s*(?:assistant|agent|bot|ai|model|helper)\b/iu', $draft)) {
            $issues[] = 'leaked_system_prompt';
            $rewritten = 'Happy to help — could you share a bit more about what you need?';
        }

        return [
            'passed' => $issues === [],
            'issues' => $issues,
            'rewritten' => $rewritten !== $draft ? $rewritten : null,
        ];
    }

    /**
     * Lightweight product existence check — verifies catalog products mentioned in the draft.
     *
     * @return array{known: list<string>, unknown: list<string>}
     */
    private function extractProductMentions(string $draft, Company $company): array
    {
        $products = Product::where('company_id', $company->id)
            ->where('status', 'active')
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower(trim($n)))
            ->filter(fn ($n) => mb_strlen($n) >= 4)
            ->all();

        $lower = mb_strtolower($draft);
        $known = [];

        foreach ($products as $name) {
            if (str_contains($lower, $name)) {
                $known[] = $name;
            }
        }

        return ['known' => $known, 'unknown' => []];
    }
}
