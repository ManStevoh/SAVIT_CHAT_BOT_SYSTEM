<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\Growth\GrowthLimitService;
use App\Services\Growth\MetaSocialService;
use App\Services\Growth\SocialOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthSocialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $accounts = SocialAccount::where('company_id', $companyId)
            ->orderBy('platform')
            ->get()
            ->map(fn (SocialAccount $a) => [
                'id' => (string) $a->id,
                'platform' => $a->platform,
                'accountName' => $a->account_name,
                'status' => $a->status,
                'connectedAt' => $a->connected_at?->toIso8601String(),
            ]);

        return response()->json($accounts->values()->all());
    }

    public function connect(Request $request, MetaSocialService $meta): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $connectedCount = SocialAccount::where('company_id', $company->id)->where('status', 'connected')->count();
        if ($connectedCount >= GrowthLimitService::getPlatformLimit($company)) {
            return response()->json(['success' => false, 'message' => 'Platform connection limit reached for your plan.'], 422);
        }

        $validated = $request->validate([
            'platform' => 'required|string|in:facebook,instagram,linkedin,tiktok,twitter',
            'accountName' => 'nullable|string|max:255',
            'pageId' => 'nullable|string|max:255',
            'externalAccountId' => 'nullable|string|max:255',
            'accessToken' => 'nullable|string',
        ]);

        $account = $meta->connectAccount($company, [
            'platform' => $validated['platform'],
            'account_name' => $validated['accountName'] ?? null,
            'page_id' => $validated['pageId'] ?? null,
            'external_account_id' => $validated['externalAccountId'] ?? null,
            'access_token' => $validated['accessToken'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'account' => [
                'id' => (string) $account->id,
                'platform' => $account->platform,
                'accountName' => $account->account_name,
                'status' => $account->status,
            ],
        ]);
    }

    public function oauthConfig(Request $request, SocialOAuthService $oauth): JsonResponse
    {
        $platforms = ['facebook', 'instagram', 'linkedin', 'tiktok', 'twitter'];

        return response()->json([
            'callbackUrl' => $oauth->callbackUrl(),
            'platforms' => collect($platforms)->map(fn ($p) => $oauth->configForPlatform($p))->values()->all(),
        ]);
    }

    public function oauthAuthorize(Request $request, string $platform, SocialOAuthService $oauth): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        if (! in_array($platform, ['facebook', 'instagram', 'linkedin', 'tiktok', 'twitter'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid platform.'], 422);
        }

        try {
            $url = $oauth->createAuthorizeUrl($company, $request->user(), $platform);

            return response()->json(['success' => true, 'authorizeUrl' => $url]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function disconnect(Request $request, SocialAccount $account): JsonResponse
    {
        if ((int) $request->user()->company_id !== (int) $account->company_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $account->update(['status' => 'disconnected', 'access_token' => null, 'refresh_token' => null]);

        return response()->json(['success' => true]);
    }
}
