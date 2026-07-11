<?php

namespace App\Services\Agent;

use App\Models\Company;

/**
 * Declarative business goals the Chief Agent optimizes for.
 */
final class BusinessGoalService
{
    /**
     * @return array<string, string> goal_key => description
     */
    public function catalog(): array
    {
        return config('agent.business_goals', []);
    }

    /**
     * @return list<string> enabled goal keys for company
     */
    public function enabledKeys(Company $company): array
    {
        $stored = $company->settings?->agent_business_goals;
        if (is_array($stored) && $stored !== []) {
            $catalog = array_keys($this->catalog());

            return array_values(array_intersect($stored, $catalog));
        }

        return array_keys($this->catalog());
    }

    public function getForPrompt(Company $company): string
    {
        $catalog = $this->catalog();
        $keys = $this->enabledKeys($company);
        if ($keys === []) {
            return '';
        }

        $lines = ['Business goals (optimize your actions toward these):'];
        foreach ($keys as $key) {
            $lines[] = "- {$key}: ".($catalog[$key] ?? $key);
        }

        return implode("\n", $lines);
    }
}
