<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Jobs\Growth\ProcessCrmFollowUpsJob;
use App\Jobs\Growth\SyncMetaAdSpendJob;
use App\Jobs\Growth\SyncMetaMetricsJob;
use App\Models\SocialAccount;
use App\Services\Growth\CrmFollowUpService;
use App\Services\Growth\GrowthOnboardingService;
use App\Services\Growth\MetaAdsService;
use App\Services\Growth\SocialOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthMetaController extends Controller
{
    public function pilotStatus(Request $request, GrowthOnboardingService $onboarding): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $checklist = $onboarding->checklist($company);
        $expiringAccounts = SocialAccount::where('company_id', $company->id)
            ->where('status', 'connected')
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<', now()->addDays(7))
            ->get()
            ->map(fn ($a) => [
                'platform' => $a->platform,
                'accountName' => $a->account_name,
                'expiresAt' => $a->token_expires_at?->toIso8601String(),
            ]);

        return response()->json([
            'isPilot' => (bool) $company->growth_pilot_at,
            'pilotSince' => $company->growth_pilot_at?->toIso8601String(),
            'demoMode' => (bool) $company->growth_demo_mode,
            'firstAttributedSaleAt' => $company->first_attributed_sale_at?->toIso8601String(),
            'onboarding' => $checklist,
            'tokenExpiryWarnings' => $expiringAccounts->values()->all(),
        ]);
    }

    public function onboarding(Request $request, GrowthOnboardingService $onboarding): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return response()->json($onboarding->checklist($company));
    }

    public function pages(Request $request, SocialOAuthService $oauth): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $platform = $request->input('platform', 'facebook');

        return response()->json(['pages' => $oauth->listPagesForCompany($company, $platform)]);
    }

    public function selectPage(Request $request, SocialOAuthService $oauth): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'platform' => 'required|string|in:facebook,instagram',
            'pageId' => 'required|string',
        ]);

        $account = $oauth->selectPage($company, $validated['platform'], $validated['pageId']);

        return response()->json([
            'success' => true,
            'account' => [
                'id' => (string) $account->id,
                'accountName' => $account->account_name,
                'status' => $account->status,
            ],
        ]);
    }

    public function adAccounts(Request $request, MetaAdsService $ads): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $account = SocialAccount::where('company_id', $company->id)
            ->whereIn('platform', ['facebook', 'instagram'])
            ->where('status', 'connected')
            ->first();

        if (! $account) {
            return response()->json(['adAccounts' => []]);
        }

        $stored = $account->metadata['ad_accounts'] ?? [];
        $discovered = $ads->discoverAdAccounts($account);

        return response()->json([
            'adAccounts' => ! empty($discovered) ? $discovered : $stored,
            'selectedAdAccountId' => $account->ad_account_id,
        ]);
    }

    public function selectAdAccount(Request $request, SocialOAuthService $oauth): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'platform' => 'required|string|in:facebook,instagram',
            'adAccountId' => 'required|string',
        ]);

        $account = $oauth->selectAdAccount($company, $validated['platform'], $validated['adAccountId']);

        return response()->json(['success' => true, 'adAccountId' => $account->ad_account_id]);
    }

    public function syncMetrics(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        SyncMetaMetricsJob::dispatch((int) $companyId);

        return response()->json(['success' => true, 'message' => 'Meta metrics sync queued.']);
    }

    public function syncAds(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        SyncMetaAdSpendJob::dispatch((int) $companyId);

        return response()->json(['success' => true, 'message' => 'Meta ad spend sync queued.']);
    }

    public function crmStatus(Request $request, CrmFollowUpService $crm): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return response()->json($crm->eligibleSummary((int) $companyId));
    }

    public function runCrmAgent(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        ProcessCrmFollowUpsJob::dispatch((int) $companyId);

        return response()->json(['success' => true, 'message' => 'CRM follow-up agent queued.']);
    }
}
