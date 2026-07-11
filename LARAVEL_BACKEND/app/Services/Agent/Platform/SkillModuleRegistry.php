<?php

namespace App\Services\Agent\Platform;

/**
 * Marketplace of installable industry skills (#31).
 */
final class SkillModuleRegistry
{
    /**
     * @return array<string, array{name: string, description: string, tools: list<string>, prompt_addon: string}>
     */
    public function catalog(): array
    {
        return config('agent.platform.skill_modules', []);
    }

    public function getForIndustry(?string $industry): ?array
    {
        $catalog = $this->catalog();
        $key = $industry && isset($catalog[$industry]) ? $industry : 'retail';

        return $catalog[$key] ?? null;
    }

    public function promptAddonForCompany(?string $industry): string
    {
        $module = $this->getForIndustry($industry);
        if ($module === null) {
            return '';
        }

        return "Industry skill module: {$module['name']}\n{$module['prompt_addon']}";
    }
}
