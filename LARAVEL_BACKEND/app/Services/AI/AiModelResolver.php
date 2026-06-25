<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Company;
use App\Models\PlatformSetting;
use App\Services\PlanLimitService;
use Illuminate\Support\Facades\Cache;

class AiModelResolver
{
    private const CACHE_TTL = 300;

    public function __construct(
        private AiLearningConfig $learningConfig,
        private CompanyAiCredentialService $credentials,
    ) {}

    public function resolve(?Company $company, string $capability = AiModel::CAPABILITY_CHAT): ?ResolvedAiModel
    {
        $providers = $this->providersWithModels();

        if ($company) {
            $settings = $company->settings;
            $mode = PlanLimitService::effectiveAiModelMode($company);

            if ($mode === 'specific' && $settings?->ai_model_id) {
                $resolved = $this->resolveModelId((int) $settings->ai_model_id, $capability, $providers, 'company_specific', $company);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
            if ($mode === 'platform_default') {
                $resolved = $this->resolvePlatformDefault($capability, $providers, $company);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        if ($company && PlanLimitService::effectiveAiModelMode($company) === 'auto') {
            $resolved = $this->resolveAuto($capability, $providers, $company);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $this->resolvePlatformDefault($capability, $providers, $company)
            ?? $this->resolveAuto($capability, $providers, $company)
            ?? $this->resolveLegacyFallback($capability, $company);
    }

    /**
     * @return \Illuminate\Support\Collection<int, AiProvider>
     */
    protected function providersWithModels()
    {
        return Cache::remember('ai_providers_with_models', self::CACHE_TTL, function () {
            return AiProvider::query()
                ->with(['models' => fn ($q) => $q->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get();
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AiProvider>  $providers
     */
    protected function resolveModelId(int $modelId, string $capability, $providers, string $source, ?Company $company): ?ResolvedAiModel
    {
        foreach ($providers as $provider) {
            if (! $this->providerIsReady($provider, $company)) {
                continue;
            }
            $model = $provider->models->first(fn (AiModel $m) => $m->id === $modelId
                && $m->capability === $capability
                && $m->is_enabled);
            if ($model) {
                return $this->wrap($provider, $model, $source, $company);
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AiProvider>  $providers
     */
    protected function resolvePlatformDefault(string $capability, $providers, ?Company $company): ?ResolvedAiModel
    {
        foreach ($providers as $provider) {
            if (! $this->providerIsReady($provider, $company)) {
                continue;
            }
            $model = $provider->models->first(fn (AiModel $m) => $m->capability === $capability
                && $m->is_enabled
                && $m->is_platform_default);
            if ($model) {
                return $this->wrap($provider, $model, 'platform_default', $company);
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AiProvider>  $providers
     */
    protected function resolveAuto(string $capability, $providers, ?Company $company): ?ResolvedAiModel
    {
        $candidates = [];
        foreach ($providers as $provider) {
            if (! $this->providerIsReady($provider, $company)) {
                continue;
            }
            foreach ($provider->models as $model) {
                if ($model->capability !== $capability || ! $model->is_enabled) {
                    continue;
                }
                $score = (float) $model->input_cost_per_million + (float) $model->output_cost_per_million;
                $candidates[] = ['provider' => $provider, 'model' => $model, 'score' => $score];
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $a['score'] <=> $b['score']);

        $pick = $candidates[0];

        return $this->wrap($pick['provider'], $pick['model'], 'auto', $company);
    }

    protected function resolveLegacyFallback(string $capability, ?Company $company): ?ResolvedAiModel
    {
        $provider = AiProvider::firstOrCreate(
            ['slug' => 'openai'],
            ['name' => 'OpenAI', 'is_enabled' => true, 'sort_order' => 0]
        );

        if ($company) {
            $cred = $this->credentials->resolve($company, $provider);
            if ($cred['key'] === null) {
                return null;
            }
        } else {
            $platform = PlatformSetting::first();
            $apiKey = $platform?->openai_api_key ?? config('openai.api_key');
            if (! $apiKey) {
                return null;
            }
            if (! $provider->api_key) {
                $provider->update(['api_key' => $apiKey, 'is_enabled' => true]);
            }
        }

        $platform = PlatformSetting::first();
        $modelKey = $platform?->openai_model
            ?? $platform?->ai_model
            ?? config('openai.model', 'gpt-4o-mini');

        if ($capability === AiModel::CAPABILITY_EMBEDDING) {
            $modelKey = $this->learningConfig->embeddingModelKey();
        }

        $model = AiModel::firstOrCreate(
            [
                'ai_provider_id' => $provider->id,
                'model_key' => $modelKey,
                'capability' => $capability,
            ],
            [
                'display_name' => $modelKey,
                'input_cost_per_million' => 0.15,
                'output_cost_per_million' => 0.60,
                'max_output_tokens' => (int) ($platform?->openai_max_tokens ?? 512),
                'is_enabled' => true,
                'is_platform_default' => true,
            ]
        );

        return $this->wrap($provider->fresh(), $model, 'legacy_fallback', $company);
    }

    protected function wrap(AiProvider $provider, AiModel $model, string $source, ?Company $company): ?ResolvedAiModel
    {
        $cred = $company
            ? $this->credentials->resolve($company, $provider)
            : $this->platformOnlyCredential($provider);

        if ($cred['key'] === null) {
            return null;
        }

        return new ResolvedAiModel(
            $provider,
            $model,
            $source,
            $cred['key'],
            $cred['source'],
            $cred['api_base_url'],
        );
    }

    /**
     * @return array{key: ?string, source: 'platform'|'company', api_base_url: ?string}
     */
    protected function platformOnlyCredential(AiProvider $provider): array
    {
        if ($provider->hasConfiguredApiKey()) {
            return ['key' => $provider->api_key, 'source' => 'platform', 'api_base_url' => $provider->api_base_url];
        }

        if ($provider->slug === 'openai') {
            $platform = PlatformSetting::first();
            $key = $platform?->openai_api_key ?? config('openai.api_key');

            return ['key' => $key ?: null, 'source' => 'platform', 'api_base_url' => $provider->api_base_url];
        }

        if ($provider->slug === 'google') {
            $key = $provider->hasConfiguredApiKey()
                ? $provider->api_key
                : config('gemini.api_key');

            return ['key' => $key ?: null, 'source' => 'platform', 'api_base_url' => $provider->api_base_url];
        }

        return ['key' => null, 'source' => 'platform', 'api_base_url' => null];
    }

    protected function providerIsReady(AiProvider $provider, ?Company $company): bool
    {
        if (! $provider->is_enabled) {
            return false;
        }

        if ($company) {
            $cred = $this->credentials->resolve($company, $provider);

            return $cred['key'] !== null;
        }

        return $this->platformOnlyCredential($provider)['key'] !== null;
    }

    public function apiKeyForProvider(AiProvider $provider, ?Company $company = null): ?string
    {
        if ($company) {
            return $this->credentials->resolve($company, $provider)['key'];
        }

        return $this->platformOnlyCredential($provider)['key'];
    }

    public static function clearCache(): void
    {
        Cache::forget('ai_providers_with_models');
        Cache::forget('platform_settings_openai');
    }
}
