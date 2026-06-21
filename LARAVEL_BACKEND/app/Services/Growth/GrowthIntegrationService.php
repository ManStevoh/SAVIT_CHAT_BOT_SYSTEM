<?php

namespace App\Services\Growth;

use App\Models\Company;
use App\Models\GrowthIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GrowthIntegrationService
{
    /**
     * @return array<int, array{provider: string, status: string, configured: bool, lastSyncedAt: ?string, message: ?string}>
     */
    public function statusForCompany(Company $company): array
    {
        $providers = ['ga4', 'email', 'website'];

        return collect($providers)->map(function (string $provider) use ($company) {
            $row = GrowthIntegration::where('company_id', $company->id)->where('provider', $provider)->first();
            $globalConfigured = $this->isGloballyConfigured($provider);

            return [
                'provider' => $provider,
                'status' => $row?->status ?? ($globalConfigured ? 'available' : 'not_configured'),
                'configured' => $globalConfigured || ($row?->status === 'connected'),
                'lastSyncedAt' => $row?->last_synced_at?->toIso8601String(),
                'message' => $row?->last_error,
            ];
        })->all();
    }

    public function connect(Company $company, string $provider, array $config): GrowthIntegration
    {
        return GrowthIntegration::updateOrCreate(
            ['company_id' => $company->id, 'provider' => $provider],
            [
                'status' => 'connected',
                'config' => $config,
                'last_error' => null,
            ]
        );
    }

    public function syncCompany(Company $company): array
    {
        $results = [];
        $integrations = GrowthIntegration::where('company_id', $company->id)->where('status', 'connected')->get();

        foreach ($integrations as $integration) {
            $results[$integration->provider] = match ($integration->provider) {
                'ga4' => $this->syncGa4($company, $integration),
                'email' => $this->syncEmail($company, $integration),
                'website' => $this->syncWebsite($company, $integration),
                default => ['success' => false, 'message' => 'Unknown provider'],
            };
        }

        return $results;
    }

    /**
     * @return array{success: bool, message: string, events?: int}
     */
    public function syncGa4(Company $company, ?GrowthIntegration $integration = null): array
    {
        $measurementId = config('growth.integrations.ga4.measurement_id');
        $apiSecret = config('growth.integrations.ga4.api_secret');

        if (! config('growth.integrations.ga4.enabled') || ! $measurementId || ! $apiSecret) {
            return ['success' => false, 'message' => 'GA4 not configured. Set GROWTH_GA4_ENABLED and credentials in .env.'];
        }

        $integration?->update(['last_synced_at' => now(), 'last_error' => null]);

        Log::info('GA4 sync placeholder', ['company_id' => $company->id, 'measurement_id' => $measurementId]);

        return [
            'success' => true,
            'message' => 'GA4 connector ready. Wire Measurement Protocol events from attribution clicks.',
            'events' => 0,
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function syncEmail(Company $company, ?GrowthIntegration $integration = null): array
    {
        if (! config('growth.integrations.email.enabled')) {
            return ['success' => false, 'message' => 'Email sync not enabled. Set GROWTH_EMAIL_SYNC_ENABLED=true.'];
        }

        $provider = config('growth.integrations.email.provider', 'mailchimp');
        $integration?->update(['last_synced_at' => now(), 'last_error' => null]);

        return [
            'success' => true,
            'message' => "Email provider ({$provider}) connector ready. Implement list sync in a future phase.",
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function syncWebsite(Company $company, ?GrowthIntegration $integration = null): array
    {
        $url = $integration?->config['site_url'] ?? null;
        if (! $url) {
            return ['success' => false, 'message' => 'No website URL configured for this company.'];
        }

        try {
            $response = Http::timeout(10)->head($url);
            $integration?->update([
                'last_synced_at' => now(),
                'last_error' => $response->successful() ? null : 'Site unreachable',
            ]);

            return [
                'success' => $response->successful(),
                'message' => $response->successful() ? 'Website reachable.' : 'Website check failed.',
            ];
        } catch (\Throwable $e) {
            $integration?->update(['last_error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function isGloballyConfigured(string $provider): bool
    {
        return match ($provider) {
            'ga4' => (bool) config('growth.integrations.ga4.enabled')
                && config('growth.integrations.ga4.measurement_id')
                && config('growth.integrations.ga4.api_secret'),
            'email' => (bool) config('growth.integrations.email.enabled'),
            'website' => true,
            default => false,
        };
    }
}
