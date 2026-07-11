<?php

namespace App\Services\Agent\Platform;

use App\Models\Company;
use App\Models\CompanyModuleInstallation;
use App\Models\MarketplaceModule;
use App\Services\Platform\EntitlementService;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * AI Marketplace — catalog, install/uninstall, entitlements.
 */
final class MarketplaceModuleService
{
    public function __construct(
        protected EntitlementService $entitlements,
    ) {}

    /**
     * @return Collection<int, MarketplaceModule>
     */
    public function activeCatalog(): Collection
    {
        return MarketplaceModule::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalogForCompany(Company $company): array
    {
        $plan = $this->entitlements->currentPlanSlug($company);
        $installed = $this->installedKeys($company);

        return $this->activeCatalog()->map(function (MarketplaceModule $module) use ($plan, $installed) {
            return [
                'moduleKey' => $module->module_key,
                'name' => $module->name,
                'description' => $module->description,
                'category' => $module->category,
                'publisher' => $module->publisher,
                'requiredPlan' => $module->required_plan,
                'tools' => $module->tools ?? [],
                'isInstalled' => in_array($module->module_key, $installed, true),
                'canInstall' => $this->canInstallPlan($plan, $module->required_plan),
                'isThirdParty' => $module->publisher === 'third_party',
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function installedForCompany(Company $company): array
    {
        $installations = CompanyModuleInstallation::query()
            ->where('company_id', $company->id)
            ->where('status', 'installed')
            ->with('module')
            ->orderByDesc('installed_at')
            ->get();

        return $installations->map(function (CompanyModuleInstallation $row) {
            $module = $row->module;

            return [
                'moduleKey' => $row->module_key,
                'name' => $module?->name ?? $row->module_key,
                'description' => $module?->description,
                'category' => $module?->category,
                'publisher' => $module?->publisher,
                'tools' => $module?->tools ?? [],
                'config' => $row->config ?? [],
                'installedAt' => $row->installed_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function install(Company $company, string $moduleKey, array $config = []): CompanyModuleInstallation
    {
        $module = MarketplaceModule::where('module_key', $moduleKey)->where('is_active', true)->first();
        if (! $module) {
            throw new InvalidArgumentException('Module not found.');
        }

        $plan = $this->entitlements->currentPlanSlug($company);
        if (! $this->canInstallPlan($plan, $module->required_plan)) {
            throw new InvalidArgumentException('Plan does not include this module.');
        }

        if ($module->publisher === 'third_party' && empty($config['webhook_base_url'])) {
            throw new InvalidArgumentException('Third-party modules require webhook_base_url in config.');
        }

        return CompanyModuleInstallation::updateOrCreate(
            [
                'company_id' => $company->id,
                'module_key' => $moduleKey,
            ],
            [
                'status' => 'installed',
                'config' => $config !== [] ? $config : null,
                'installed_at' => now(),
            ],
        );
    }

    public function uninstall(Company $company, string $moduleKey): bool
    {
        return CompanyModuleInstallation::where('company_id', $company->id)
            ->where('module_key', $moduleKey)
            ->delete() > 0;
    }

    /**
     * @return list<string>
     */
    public function installedKeys(Company $company): array
    {
        return CompanyModuleInstallation::query()
            ->where('company_id', $company->id)
            ->where('status', 'installed')
            ->pluck('module_key')
            ->all();
    }

    public function canInstallPlan(string $currentPlan, ?string $requiredPlan): bool
    {
        if ($requiredPlan === null || $requiredPlan === '') {
            return true;
        }

        $ranks = config('agent.marketplace.plan_rank', []);
        $current = $ranks[$currentPlan] ?? 1;
        $required = $ranks[$requiredPlan] ?? 1;

        return $current >= $required;
    }

    /**
     * @return array<string, mixed>
     */
    public function sdkManifest(): array
    {
        return [
            'sdk_version' => '1',
            'name' => 'SAVIT Agent SDK',
            'description' => 'Publish third-party agent tools via webhook modules.',
            'authentication' => [
                'type' => 'api_key',
                'header' => 'Authorization',
                'format' => 'Bearer {api_key}',
            ],
            'module_install' => [
                'config_fields' => [
                    'webhook_base_url' => 'HTTPS base URL for tool invocations',
                    'webhook_secret' => 'Optional HMAC secret sent as X-Savit-Signature',
                ],
            ],
            'tool_invocation' => [
                'method' => 'POST',
                'path' => '/tools/{tool_name}',
                'body' => [
                    'arguments' => 'object — tool arguments from the agent',
                    'context' => 'object — company_id, chat_id, customer_phone',
                ],
                'response' => [
                    'result' => 'object — JSON-serializable tool result',
                ],
            ],
            'example_manifest' => [
                'sdk_version' => '1',
                'module_key' => 'my_agent',
                'type' => 'third_party',
                'tools' => [
                    [
                        'name' => 'my_tool',
                        'description' => 'What the tool does',
                        'parameters' => ['type' => 'object', 'properties' => []],
                    ],
                ],
            ],
        ];
    }
}
