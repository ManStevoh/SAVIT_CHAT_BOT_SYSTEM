<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CognitiveEpisode;
use App\Models\ExecutivePlan;
use App\Models\KnowledgeArtifact;
use App\Models\StrategicMemory;
use App\Models\ToolProposal;
use App\Services\Agent\Cognitive\CausalReasoningService;
use App\Services\Agent\Cognitive\DigitalWorkforceRegistry;
use App\Services\Agent\Cognitive\ExecutivePlanningService;
use App\Services\Agent\Cognitive\ForecastService;
use App\Services\Agent\Cognitive\SimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CognitiveAiController extends Controller
{
    public function dashboard(
        Request $request,
        DigitalWorkforceRegistry $workforce,
        ForecastService $forecast,
        CausalReasoningService $causal,
    ): JsonResponse {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $recentEpisode = CognitiveEpisode::where('company_id', $company->id)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'architecture' => 'perception → debate → reason → act → critique → memory',
            'workforce' => $workforce->dashboardPayload(),
            'forecast' => $forecast->demandForecast($company),
            'causalAnalysis' => $causal->analyzeSalesChange($company),
            'recentEpisode' => $recentEpisode ? [
                'confidence' => $recentEpisode->confidence,
                'confidence_action' => $recentEpisode->confidence_action,
                'perception' => $recentEpisode->perception,
                'outcome' => $recentEpisode->outcome,
            ] : null,
            'counts' => [
                'strategic_memories' => StrategicMemory::where('company_id', $company->id)->count(),
                'tool_proposals' => ToolProposal::where('company_id', $company->id)->where('status', 'proposed')->count(),
                'knowledge_artifacts' => KnowledgeArtifact::where('company_id', $company->id)->count(),
                'executive_plans' => ExecutivePlan::where('company_id', $company->id)->where('status', 'active')->count(),
            ],
        ]);
    }

    public function strategicMemories(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $items = StrategicMemory::where('company_id', $company->id)
            ->orderByDesc('success_score')
            ->limit(50)
            ->get();

        return response()->json(['memories' => $items]);
    }

    public function createPlan(Request $request, ExecutivePlanningService $planning): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'goal' => 'required|string|max:500',
        ]);

        $result = $planning->createPlan($company, $validated['goal']);

        return response()->json([
            'plan' => $result['plan'],
            'breakdown' => $result['breakdown'],
        ], 201);
    }

    public function simulate(Request $request, SimulationService $simulation): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'scenario_type' => 'required|string|max:80',
            'inputs' => 'nullable|array',
        ]);

        $result = $simulation->simulate(
            $company,
            $validated['scenario_type'],
            $validated['inputs'] ?? [],
        );

        return response()->json($result);
    }
}
