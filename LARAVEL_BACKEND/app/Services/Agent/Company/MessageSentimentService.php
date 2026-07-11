<?php

namespace App\Services\Agent\Company;

/**
 * Fast emotional signal detection before reasoning (no LLM).
 */
final class MessageSentimentService
{
    /**
     * @return array{label: string, score: float, cues: list<string>}
     */
    public function detect(string $message): array
    {
        $lower = mb_strtolower(trim($message));
        if ($lower === '') {
            return ['label' => 'neutral', 'score' => 0.0, 'cues' => []];
        }

        $frustrated = ['forever', 'ridiculous', 'unacceptable', 'angry', 'worst', 'still waiting', 'taking forever', 'complaint', 'disappointed', 'fraud', 'scam'];
        $urgent = ['urgent', 'asap', 'today', 'now', 'immediately', 'exam tomorrow', 'deadline'];
        $positive = ['thank', 'thanks', 'great', 'awesome', 'perfect', 'love', 'excellent'];

        $cues = [];
        $score = 0.0;

        foreach ($frustrated as $w) {
            if (str_contains($lower, $w)) {
                $cues[] = $w;
                $score -= 0.35;
            }
        }
        foreach ($urgent as $w) {
            if (str_contains($lower, $w)) {
                $cues[] = $w;
                $score -= 0.15;
            }
        }
        foreach ($positive as $w) {
            if (str_contains($lower, $w)) {
                $cues[] = $w;
                $score += 0.25;
            }
        }

        if (str_contains($lower, '!') && $score < 0) {
            $score -= 0.1;
        }

        $label = match (true) {
            $score <= -0.35 => 'frustrated',
            $score <= -0.15 => 'concerned',
            $score >= 0.25 => 'positive',
            default => 'neutral',
        };

        return ['label' => $label, 'score' => round(max(-1, min(1, $score)), 2), 'cues' => array_values(array_unique($cues))];
    }

    public function guidanceForPrompt(array $sentiment): string
    {
        if (($sentiment['label'] ?? 'neutral') === 'neutral') {
            return '';
        }

        $label = $sentiment['label'];
        $hint = match ($label) {
            'frustrated' => 'Customer sounds frustrated — acknowledge feelings first, apologize if appropriate, then solve. Consider transfer_to_human if unresolved.',
            'concerned' => 'Customer sounds concerned or urgent — prioritize clarity and speed.',
            'positive' => 'Customer tone is positive — maintain warmth; good moment for relevant upsell if natural.',
            default => '',
        };

        return $hint !== '' ? "Emotional context: {$label}. {$hint}" : '';
    }
}
