<?php

namespace App\Services\AI;

use App\Models\ConversationLearningSample;

/**
 * Rerank hybrid retrieval results using feedback quality signals.
 */
final class LearningRerankerService
{
    /**
     * @param  array<int, array{sample: ConversationLearningSample, score: float}>  $ranked
     * @return array<int, array{sample: ConversationLearningSample, score: float}>
     */
    public function rerank(array $ranked): array
    {
        if (count($ranked) <= 1) {
            return $ranked;
        }

        foreach ($ranked as &$row) {
            $sample = $row['sample'];
            $pos = (int) ($sample->positive_feedback_count ?? 0);
            $neg = (int) ($sample->negative_feedback_count ?? 0);
            $total = $pos + $neg;
            $quality = $total > 0 ? ($pos / $total) : 0.5;
            $usageBoost = 1 + (min((int) $sample->use_count, 50) * 0.01);
            $qualityBoost = 0.75 + ($quality * 0.5);
            $row['score'] = $row['score'] * $usageBoost * $qualityBoost;
        }
        unset($row);

        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $ranked;
    }
}
