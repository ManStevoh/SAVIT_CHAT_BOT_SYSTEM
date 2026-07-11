<?php

namespace App\Services\Agent\Company;

use App\Models\Company;

/**
 * Digital twin: mission, brand, strategy — the AI's model of the business.
 */
final class CompanyDigitalTwinService
{
    /**
     * @return array<string, mixed>
     */
    public function getTwin(Company $company): array
    {
        $stored = $company->settings?->digital_twin;
        if (! is_array($stored)) {
            $stored = [];
        }

        return array_merge($this->defaults($company), $stored);
    }

    public function getForPrompt(Company $company): string
    {
        $twin = $this->getTwin($company);
        $lines = ['Business digital twin (how you represent this company):'];

        foreach ([
            'mission' => 'Mission',
            'brand_voice' => 'Brand voice',
            'sales_strategy' => 'Sales strategy',
            'pricing_rules' => 'Pricing rules',
            'competitors' => 'Competitors',
            'target_customers' => 'Target customers',
        ] as $key => $label) {
            $val = trim((string) ($twin[$key] ?? ''));
            if ($val !== '') {
                $lines[] = "- {$label}: {$val}";
            }
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }

    /**
     * @return array<string, string>
     */
    private function defaults(Company $company): array
    {
        $settings = $company->settings;

        return array_filter([
            'mission' => "Help customers of {$company->name} buy and get support efficiently",
            'brand_voice' => $settings?->ai_tone ?? 'balanced',
            'sales_strategy' => 'Recommend relevant products; never pressure; clarify total cost upfront',
        ]);
    }
}
