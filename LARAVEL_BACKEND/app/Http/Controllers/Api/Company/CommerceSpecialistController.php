<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CommerceAgentRun;
use App\Services\Agent\Specialists\CommerceSpecialistOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommerceSpecialistController extends Controller
{
    public function index(Request $request, CommerceSpecialistOrchestrator $orchestrator): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $runs = CommerceAgentRun::where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($run) => $orchestrator->formatRun($run));

        return response()->json(['runs' => $runs]);
    }

    public function runPipeline(Request $request, CommerceSpecialistOrchestrator $orchestrator): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $runs = $orchestrator->dispatchBackgroundPipeline($company, null, $request->input('input', []));

        return response()->json(['runs' => $runs], 202);
    }
}
