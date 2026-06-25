<?php

namespace App\Services\AI;

use App\Models\AiProvider;
use App\Models\Company;
use App\Models\CompanyAiProvider;
use App\Models\PlatformSetting;
use App\Services\PlanLimitService;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves per-tenant (BYOK) vs platform AI API credentials.
 */
final class CompanyAiCredentialService
{
    public const MODE_PLATFORM = 'platform';

    public const MODE_COMPANY = 'company';

    public const MODE_COMPANY_PREFERRED = 'company_preferred';

    public function credentialMode(Company $company): string
    {
        return PlanLimitService::effectiveCredentialMode($company);
    }

    /**
     * Stored preference (ignores plan clamping) — for settings UI.
     */
    public function storedCredentialMode(Company $company): string
    {
        $company->loadMissing('settings');
        $mode = $company->settings?->ai_credential_mode ?? self::MODE_PLATFORM;

        return in_array($mode, [self::MODE_PLATFORM, self::MODE_COMPANY, self::MODE_COMPANY_PREFERRED], true)
            ? $mode
            : self::MODE_PLATFORM;
    }

    /**
     * @return array{key: ?string, source: 'platform'|'company', api_base_url: ?string}
     */
    public function resolve(Company $company, AiProvider $provider): array
    {
        $mode = $this->credentialMode($company);
        $companyRow = $this->companyProviderRow($company->id, $provider->id);

        if ($companyRow && $companyRow->is_enabled && $companyRow->hasConfiguredApiKey()) {
            return [
                'key' => $companyRow->api_key,
                'source' => 'company',
                'api_base_url' => $companyRow->api_base_url,
            ];
        }

        if ($mode === self::MODE_COMPANY) {
            return ['key' => null, 'source' => 'company', 'api_base_url' => null];
        }

        $platformKey = $this->platformKeyForProvider($provider);
        if ($platformKey !== null) {
            return [
                'key' => $platformKey,
                'source' => 'platform',
                'api_base_url' => $provider->api_base_url,
            ];
        }

        return ['key' => null, 'source' => 'platform', 'api_base_url' => null];
    }

    public function companyProviderRow(int $companyId, int $providerId): ?CompanyAiProvider
    {
        return Cache::remember(
            "company_ai_provider:{$companyId}:{$providerId}",
            300,
            fn () => CompanyAiProvider::query()
                ->where('company_id', $companyId)
                ->where('ai_provider_id', $providerId)
                ->first()
        );
    }

    public static function clearCacheForCompany(int $companyId): void
    {
        foreach (AiProvider::query()->pluck('id') as $providerId) {
            Cache::forget("company_ai_provider:{$companyId}:{$providerId}");
        }
    }

    protected function platformKeyForProvider(AiProvider $provider): ?string
    {
        if ($provider->hasConfiguredApiKey()) {
            return $provider->api_key;
        }

        if ($provider->slug === 'openai') {
            $platform = PlatformSetting::first();

            return $platform?->openai_api_key ?? config('openai.api_key') ?: null;
        }

        if ($provider->slug === 'google') {
            return config('gemini.api_key') ?: null;
        }

        return null;
    }
}
