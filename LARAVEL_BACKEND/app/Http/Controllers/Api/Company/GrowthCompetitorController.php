<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CompetitorProfile;
use App\Models\CompetitorSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthCompetitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $profiles = CompetitorProfile::where('company_id', $companyId)
            ->with(['snapshots' => fn ($q) => $q->latest('recorded_at')->limit(1)])
            ->orderBy('account_name')
            ->get()
            ->map(fn (CompetitorProfile $p) => [
                'id' => (string) $p->id,
                'platform' => $p->platform,
                'accountName' => $p->account_name,
                'accountUrl' => $p->account_url,
                'isActive' => $p->is_active,
                'latestSnapshot' => $p->snapshots->first() ? [
                    'followerCount' => $p->snapshots->first()->follower_count,
                    'avgEngagement' => (float) $p->snapshots->first()->avg_engagement,
                    'recordedAt' => $p->snapshots->first()->recorded_at?->toIso8601String(),
                ] : null,
            ]);

        return response()->json($profiles->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'platform' => 'required|string|in:facebook,instagram,linkedin,tiktok,twitter',
            'accountName' => 'required|string|max:255',
            'accountUrl' => 'nullable|url|max:500',
            'externalId' => 'nullable|string|max:255',
        ]);

        $profile = CompetitorProfile::create([
            'company_id' => $companyId,
            'platform' => $validated['platform'],
            'account_name' => $validated['accountName'],
            'account_url' => $validated['accountUrl'] ?? null,
            'external_id' => $validated['externalId'] ?? null,
            'is_active' => true,
        ]);

        CompetitorSnapshot::create([
            'competitor_profile_id' => $profile->id,
            'recorded_at' => now(),
            'notes' => ['message' => 'Profile added. Connect official APIs for automated competitor metrics.'],
        ]);

        return response()->json(['success' => true, 'id' => (string) $profile->id]);
    }

    public function destroy(Request $request, CompetitorProfile $competitor): JsonResponse
    {
        if ((int) $request->user()->company_id !== (int) $competitor->company_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $competitor->delete();

        return response()->json(['success' => true]);
    }
}
