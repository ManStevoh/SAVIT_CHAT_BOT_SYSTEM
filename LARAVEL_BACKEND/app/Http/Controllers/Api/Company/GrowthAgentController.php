<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\GrowthAgentRun;
use App\Services\Growth\GrowthAgentOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthAgentController extends Controller
{
    public function index(Request $request, GrowthAgentOrchestrator $orchestrator): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $runs = GrowthAgentRun::where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn ($r) => $orchestrator->formatRun($r));

        return response()->json($runs->values()->all());
    }

    public function runPipeline(Request $request, GrowthAgentOrchestrator $orchestrator): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $input = $request->validate([
            'topic' => 'nullable|string|max:500',
            'platform' => 'nullable|string|max:32',
            'audience' => 'nullable|string|max:500',
        ]);

        $runs = $orchestrator->dispatchPipeline($company, $input);

        return response()->json(['success' => true, 'runs' => $runs]);
    }
}
