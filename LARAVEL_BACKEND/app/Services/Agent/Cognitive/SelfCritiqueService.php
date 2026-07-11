<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Company;

/**
 * Self-critique (#40) — internal review before sending to customer.
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

        if ($emotion === 'disappointed') {
            $hasEmpathy = str_contains($lower, 'sorry') || str_contains($lower, 'apolog')
                || str_contains($lower, 'understand') || str_contains($lower, 'hear you');
            if (! $hasEmpathy) {
                $issues[] = 'missing_empathy_for_disappointed_customer';
                $rewritten = "I'm sorry to hear that. ".$rewritten;
            }
        }

        if ($topic === 'refund' || ($perception['risk'] ?? '') === 'possible return') {
            if (! str_contains($lower, 'policy') && ! str_contains($lower, 'help') && ! str_contains($lower, 'resolve')) {
                $issues[] = 'refund_topic_needs_clear_resolution_path';
            }
        }

        if (mb_strlen($draft) > (int) config('agent.max_output_chars', 1200)) {
            $issues[] = 'reply_too_long';
            $rewritten = mb_substr($rewritten, 0, (int) config('agent.max_output_chars', 1200) - 3).'…';
        }

        $forbidden = ['internal reasoning', 'specialist council', 'confidence:', 'perception (internal'];
        foreach ($forbidden as $phrase) {
            if (str_contains(mb_strtolower($draft), $phrase)) {
                $issues[] = 'leaked_internal_reasoning';
            }
        }

        return [
            'passed' => $issues === [],
            'issues' => $issues,
            'rewritten' => $rewritten !== $draft ? $rewritten : null,
        ];
    }
}
