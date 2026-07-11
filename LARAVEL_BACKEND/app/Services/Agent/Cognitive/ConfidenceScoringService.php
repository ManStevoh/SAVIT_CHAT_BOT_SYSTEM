<?php

namespace App\Services\Agent\Cognitive;

/**
 * Confidence scoring (#39) — the AI knows when it doesn't know.
 */
final class ConfidenceScoringService
{
    /**
     * @param  array<string, mixed>  $perception
     * @param  array<string, mixed>  $reasoning
     */
    public function score(array $perception, array $reasoning): float
    {
        $confidence = 0.55;

        $trace = $reasoning['trace'] ?? null;
        if (is_array($trace)) {
            $confidence += 0.15;
            if (! empty($trace['understanding'])) {
                $confidence += 0.1;
            }
            if (! empty($trace['chosen_plan'])) {
                $confidence += 0.1;
            }
            if (! empty($trace['missing_info']) && is_array($trace['missing_info']) && count($trace['missing_info']) > 0) {
                $confidence -= 0.15;
            }
        } else {
            $confidence -= 0.1;
        }

        $topic = $perception['topic'] ?? 'general';
        if ($topic !== 'general') {
            $confidence += 0.08;
        }

        $urgency = $perception['urgency'] ?? 'low';
        if ($urgency === 'high' && ($perception['risk'] ?? '') !== 'low') {
            $confidence -= 0.12;
        }

        $emotion = $perception['emotion'] ?? 'neutral';
        if ($emotion === 'disappointed') {
            $confidence -= 0.08;
        }

        if (($perception['risk'] ?? '') === 'trust risk') {
            $confidence -= 0.2;
        }

        return round(max(0.1, min(0.99, $confidence)), 2);
    }

    public function actionForScore(float $confidence): string
    {
        $auto = (float) config('agent.cognitive.confidence_auto_respond', 0.7);
        $clarify = (float) config('agent.cognitive.confidence_clarify', 0.45);

        if ($confidence >= $auto) {
            return 'auto_respond';
        }
        if ($confidence >= $clarify) {
            return 'clarify';
        }

        return 'escalate';
    }

    public function guidanceForPrompt(float $confidence, string $action): string
    {
        $pct = (int) round($confidence * 100);

        return match ($action) {
            'auto_respond' => "Confidence: {$pct}% — respond directly with your best plan.",
            'clarify' => "Confidence: {$pct}% — ask one clarifying question before committing.",
            'escalate' => "Confidence: {$pct}% — prefer transfer_to_human or ask for essential missing details.",
            default => '',
        };
    }
}
