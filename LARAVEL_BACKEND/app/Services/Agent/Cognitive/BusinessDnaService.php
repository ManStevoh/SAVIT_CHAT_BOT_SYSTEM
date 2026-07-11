<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Company;

/**
 * Business DNA (#41) — tone, values, risk tolerance per business.
 */
final class BusinessDnaService
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(Company $company): array
    {
        $company->loadMissing('settings');
        $stored = $company->settings?->business_dna;
        if (is_array($stored) && $stored !== []) {
            return $stored;
        }

        $industry = $company->industry ?? 'retail';
        $defaults = config('agent.cognitive.business_dna_defaults', []);

        return $defaults[$industry] ?? $defaults['other'] ?? [
            'tone' => 'professional and friendly',
            'values' => ['honesty', 'customer care', 'fair pricing'],
            'risk_tolerance' => 'medium',
            'service_philosophy' => 'Solve problems quickly with clear communication',
            'escalation_culture' => 'Escalate when policy or refund authority is unclear',
        ];
    }

    public function getForPrompt(Company $company): string
    {
        $dna = $this->resolve($company);
        $parts = ['Business DNA (shape every reply):'];
        foreach (['tone', 'values', 'risk_tolerance', 'service_philosophy', 'escalation_culture', 'communication_style'] as $key) {
            if (! empty($dna[$key])) {
                $value = is_array($dna[$key]) ? implode(', ', $dna[$key]) : (string) $dna[$key];
                $parts[] = ucfirst(str_replace('_', ' ', $key)).': '.$value;
            }
        }

        $aiTone = $company->settings?->ai_tone;
        if ($aiTone) {
            $parts[] = 'Configured AI tone: '.$aiTone;
        }

        return implode("\n", $parts);
    }
}
