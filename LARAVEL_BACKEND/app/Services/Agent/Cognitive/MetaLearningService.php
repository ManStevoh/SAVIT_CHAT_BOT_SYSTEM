<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Company;
use App\Models\PlatformIntelligencePattern;

/**
 * Meta learning (#43, #55) — transfer generalized patterns without exposing tenant data.
 */
final class MetaLearningService
{
    public function guidanceForCompany(Company $company): string
    {
        $industry = $company->industry ?? 'other';
        $patterns = PlatformIntelligencePattern::query()
            ->orderByDesc('evidence_count')
            ->limit(5)
            ->get();

        $relevant = $patterns->filter(function ($p) use ($industry) {
            $industries = $p->industries ?? [];

            return $industries === [] || in_array($industry, $industries, true) || in_array('all', $industries, true);
        });

        if ($relevant->isEmpty()) {
            return '';
        }

        $parts = ['Platform intelligence (generalized patterns — no private data):'];
        foreach ($relevant as $pattern) {
            $parts[] = '- '.$pattern->description.' (evidence: '.$pattern->evidence_count.' businesses)';
        }

        return implode("\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function recordPattern(
        string $patternKey,
        string $patternType,
        string $description,
        array $industries = ['all'],
        array $metrics = [],
    ): PlatformIntelligencePattern {
        $existing = PlatformIntelligencePattern::where('pattern_key', $patternKey)->first();
        if ($existing) {
            $existing->update([
                'evidence_count' => $existing->evidence_count + 1,
                'metrics' => $metrics ?: $existing->metrics,
            ]);

            return $existing->fresh();
        }

        return PlatformIntelligencePattern::create([
            'pattern_key' => $patternKey,
            'pattern_type' => $patternType,
            'description' => $description,
            'evidence_count' => 1,
            'industries' => $industries,
            'metrics' => $metrics,
        ]);
    }
}
