<?php

namespace App\Services\Agent\Platform;

use App\Models\Company;
use App\Models\CompanyModuleInstallation;
use App\Models\MarketplaceModule;

/**
 * Marketplace of installable industry skills (#31) + company installations.
 */
final class SkillModuleRegistry
{
    public function __construct(
        protected MarketplaceModuleService $marketplace,
    ) {}

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

    public function promptAddonsForCompany(Company $company): string
    {
        $company->loadMissing('settings');
        $installed = $this->installedModules($company);

        if ($installed->isEmpty()) {
            return $this->promptAddonForCompany($company->industry ?? 'retail');
        }

        $parts = [];
        foreach ($installed as $module) {
            $addon = trim((string) ($module->prompt_addon ?? ''));
            if ($addon === '') {
                continue;
            }
            $parts[] = "Installed module: {$module->name}\n{$addon}";
        }

        return implode("\n\n", $parts);
    }

    /**
     * @return list<string>
     */
    public function allowedToolNamesForCompany(Company $company): array
    {
        $installed = $this->installedModules($company);
        if ($installed->isEmpty()) {
            return [];
        }

        $names = config('agent.marketplace.core_tools', []);
        foreach ($installed as $module) {
            foreach ($module->tools ?? [] as $tool) {
                if (is_string($tool) && $tool !== '') {
                    $names[] = $tool;
                }
            }
            $manifest = is_array($module->manifest) ? $module->manifest : [];
            foreach ($manifest['tools'] ?? [] as $tool) {
                if (is_array($tool) && ! empty($tool['name'])) {
                    $names[] = (string) $tool['name'];
                }
            }
        }

        return array_values(array_unique($names));
    }

    public function hasInstalledModules(Company $company): bool
    {
        return CompanyModuleInstallation::where('company_id', $company->id)
            ->where('status', 'installed')
            ->exists();
    }

    /**
     * @return \Illuminate\Support\Collection<int, MarketplaceModule>
     */
    private function installedModules(Company $company): \Illuminate\Support\Collection
    {
        $keys = $this->marketplace->installedKeys($company);
        if ($keys === []) {
            return collect();
        }

        return MarketplaceModule::query()
            ->whereIn('module_key', $keys)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
