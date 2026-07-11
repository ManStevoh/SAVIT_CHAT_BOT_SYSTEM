<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\AgentActionRequest;
use App\Models\BusinessHealthScore;
use App\Models\BusinessOpportunity;
use App\Services\Agent\Platform\AgentApprovalService;
use App\Services\Agent\Platform\BusinessWorldModelService;
use App\Services\Agent\Platform\ExecutiveBriefService;
use App\Services\Agent\Platform\OpportunityDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutiveAiController extends Controller
{
    public function dashboard(
        Request $request,
        BusinessWorldModelService $world,
        ExecutiveBriefService $executive,
    ): JsonResponse {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $health = BusinessHealthScore::where('company_id', $company->id)
            ->orderByDesc('score_date')
            ->first();

        return response()->json([
            'worldModel' => $world->build($company),
            'healthScore' => $health ? [
                'overall' => $health->overall_score,
                'factors' => $health->factors,
                'summary' => $health->summary,
                'date' => $health->score_date->toDateString(),
            ] : null,
            'topDecisions' => $executive->topDecisionsForCompany($company),
            'pendingApprovals' => AgentActionRequest::where('company_id', $company->id)
                ->where('status', 'pending')->count(),
            'openOpportunities' => BusinessOpportunity::where('company_id', $company->id)
                ->where('status', 'open')->count(),
        ]);
    }

    public function opportunities(Request $request, OpportunityDetectionService $detector): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $items = BusinessOpportunity::where('company_id', $company->id)
            ->where('status', 'open')
            ->orderByDesc('detected_at')
            ->limit(30)
            ->get();

        if ($items->isEmpty()) {
            $detector->detectForCompany($company);
            $items = BusinessOpportunity::where('company_id', $company->id)
                ->where('status', 'open')
                ->orderByDesc('detected_at')
                ->limit(30)
                ->get();
        }

        return response()->json(['opportunities' => $items]);
    }

    public function pendingApprovals(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $items = AgentActionRequest::where('company_id', $company->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['approvals' => $items]);
    }

    public function approve(Request $request, int $id, AgentApprovalService $approval): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $item = AgentActionRequest::where('company_id', $company->id)->where('id', $id)->first();
        if (! $item) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $result = $approval->approve($item, $request->user());

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'result' => $result['result'] ?? $result,
            'approval' => $item->fresh(),
        ]);
    }

    public function reject(Request $request, int $id, AgentApprovalService $approval): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $item = AgentActionRequest::where('company_id', $company->id)->where('id', $id)->first();
        if (! $item) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate(['reason' => 'nullable|string|max:500']);

        return response()->json([
            'approval' => $approval->reject($item, $request->user(), $validated['reason'] ?? null),
        ]);
    }
}
