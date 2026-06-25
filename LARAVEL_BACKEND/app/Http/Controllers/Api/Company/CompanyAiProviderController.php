<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\CompanyAiProvider;
use App\Services\AI\AiModelResolver;
use App\Services\AI\CompanyAiCredentialService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyAiProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $company->loadMissing('settings');
        $plan = PlanLimitService::getCurrentPlanSlug($company);
        $credentials = app(CompanyAiCredentialService::class);

        $rows = CompanyAiProvider::query()
            ->where('company_id', $company->id)
            ->get()
            ->keyBy('ai_provider_id');

        $providers = AiProvider::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (AiProvider $provider) use ($rows, $company, $credentials) {
                $row = $rows->get($provider->id);
                $cred = $credentials->resolve($company, $provider);

                return [
                    'slug' => $provider->slug,
                    'name' => $provider->name,
                    'apiKeyConfigured' => $row?->hasConfiguredApiKey() ?? false,
                    'apiBaseUrl' => $row?->api_base_url,
                    'isEnabled' => $row?->is_enabled ?? false,
                    'verifiedAt' => $row?->verified_at?->toIso8601String(),
                    'effectiveKeySource' => $cred['source'],
                ];
            });

        return response()->json([
            'credentialMode' => $credentials->storedCredentialMode($company),
            'effectiveCredentialMode' => PlanLimitService::effectiveCredentialMode($company),
            'aiPlanCapabilities' => PlanLimitService::aiPlanCapabilities($plan),
            'providers' => $providers,
        ]);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $plan = PlanLimitService::getCurrentPlanSlug($company);

        if (array_key_exists('credentialMode', $request->all()) || $request->filled('apiKey')) {
            if (! PlanLimitService::planAllowsByok($plan)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bring-your-own API keys are available on Professional and Enterprise plans.',
                    'code' => 'plan_byok_restricted',
                ], 422);
            }
        }

        $provider = AiProvider::query()->where('slug', $slug)->firstOrFail();
        $validated = $request->validate([
            'apiKey' => 'nullable|string|max:500',
            'apiBaseUrl' => 'nullable|string|max:255',
            'isEnabled' => 'sometimes|boolean',
            'credentialMode' => 'sometimes|string|in:platform,company,company_preferred',
        ]);

        if (array_key_exists('credentialMode', $validated)) {
            if (! PlanLimitService::isCredentialModeAllowed($plan, $validated['credentialMode'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your plan does not allow this credential mode. Enterprise required for company-keys-only mode.',
                    'code' => 'plan_credential_mode_restricted',
                ], 422);
            }
            $settings = $company->settings()->firstOrCreate(['company_id' => $company->id]);
            $settings->update(['ai_credential_mode' => $validated['credentialMode']]);
        }

        $row = CompanyAiProvider::query()->firstOrNew([
            'company_id' => $company->id,
            'ai_provider_id' => $provider->id,
        ]);

        if (array_key_exists('apiKey', $validated) && filled($validated['apiKey'])) {
            $row->api_key = $validated['apiKey'];
            $row->verified_at = now();
        }
        if (array_key_exists('apiBaseUrl', $validated)) {
            $row->api_base_url = $validated['apiBaseUrl'];
        }
        if (array_key_exists('isEnabled', $validated)) {
            $row->is_enabled = $validated['isEnabled'];
        }
        $row->save();

        CompanyAiCredentialService::clearCacheForCompany($company->id);
        AiModelResolver::clearCache();

        return response()->json(['success' => true, 'message' => 'AI provider settings saved.']);
    }
}
