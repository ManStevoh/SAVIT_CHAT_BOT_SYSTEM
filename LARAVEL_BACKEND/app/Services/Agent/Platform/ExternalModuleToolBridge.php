<?php

namespace App\Services\Agent\Platform;

use App\Models\Company;
use App\Models\CompanyModuleInstallation;
use App\Models\MarketplaceModule;
use App\Services\Agent\AgentToolContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Executes third-party marketplace tools via company webhook configuration.
 */
final class ExternalModuleToolBridge
{
    public function __construct(
        protected MarketplaceModuleService $marketplace,
    ) {}

    public function canExecute(Company $company, string $toolName): bool
    {
        return $this->resolveInvocation($company, $toolName) !== null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(Company $company, string $toolName, AgentToolContext $context, array $arguments): array
    {
        $resolved = $this->resolveInvocation($company, $toolName);
        if ($resolved === null) {
            return ['error' => 'External tool not available for this company.'];
        }

        ['url' => $url, 'secret' => $secret, 'module_key' => $moduleKey] = $resolved;

        try {
            $request = Http::timeout(20)->acceptJson();
            if ($secret !== '') {
                $request = $request->withHeaders(['X-Savit-Signature' => hash_hmac('sha256', json_encode($arguments) ?: '', $secret)]);
            }

            $response = $request->post($url, [
                'tool' => $toolName,
                'module_key' => $moduleKey,
                'arguments' => $arguments,
                'context' => [
                    'company_id' => $company->id,
                    'chat_id' => $context->chat->id,
                    'customer_phone' => $context->customerPhone,
                ],
            ]);

            if (! $response->successful()) {
                return ['error' => 'External tool webhook returned HTTP '.$response->status()];
            }

            $body = $response->json();
            if (is_array($body) && isset($body['result']) && is_array($body['result'])) {
                return $body['result'];
            }
            if (is_array($body)) {
                return $body;
            }

            return ['error' => 'Invalid external tool response.'];
        } catch (\Throwable $e) {
            Log::warning('External module tool failed', [
                'company_id' => $company->id,
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);

            return ['error' => mb_substr($e->getMessage(), 0, 300)];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openAiDefinitionsForCompany(Company $company): array
    {
        $definitions = [];
        $keys = $this->marketplace->installedKeys($company);
        if ($keys === []) {
            return [];
        }

        $modules = MarketplaceModule::query()
            ->whereIn('module_key', $keys)
            ->where('publisher', 'third_party')
            ->get();

        foreach ($modules as $module) {
            $manifest = is_array($module->manifest) ? $module->manifest : [];
            foreach ($manifest['tools'] ?? [] as $tool) {
                if (! is_array($tool) || empty($tool['name'])) {
                    continue;
                }
                $definitions[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => (string) $tool['name'],
                        'description' => (string) ($tool['description'] ?? 'External marketplace tool'),
                        'parameters' => is_array($tool['parameters'] ?? null)
                            ? $tool['parameters']
                            : ['type' => 'object', 'properties' => []],
                    ],
                ];
            }
        }

        return $definitions;
    }

    /**
     * @return array{url: string, secret: string, module_key: string}|null
     */
    private function resolveInvocation(Company $company, string $toolName): ?array
    {
        $installations = CompanyModuleInstallation::query()
            ->where('company_id', $company->id)
            ->where('status', 'installed')
            ->get();

        foreach ($installations as $installation) {
            $module = MarketplaceModule::where('module_key', $installation->module_key)
                ->where('publisher', 'third_party')
                ->first();
            if (! $module) {
                continue;
            }

            $manifest = is_array($module->manifest) ? $module->manifest : [];
            foreach ($manifest['tools'] ?? [] as $tool) {
                if (! is_array($tool) || ($tool['name'] ?? '') !== $toolName) {
                    continue;
                }

                $config = is_array($installation->config) ? $installation->config : [];
                $base = rtrim((string) ($config['webhook_base_url'] ?? ''), '/');
                if ($base === '') {
                    continue;
                }

                return [
                    'url' => $base.'/tools/'.$toolName,
                    'secret' => (string) ($config['webhook_secret'] ?? ''),
                    'module_key' => $module->module_key,
                ];
            }
        }

        return null;
    }
}
