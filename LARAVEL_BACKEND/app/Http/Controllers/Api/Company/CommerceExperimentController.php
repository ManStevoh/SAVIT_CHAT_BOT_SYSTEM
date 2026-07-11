<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CommerceExperiment;
use App\Services\Agent\Platform\CommerceExperimentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommerceExperimentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $items = CommerceExperiment::where('company_id', $company->id)
            ->with('variants')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json(['experiments' => $items]);
    }

    public function store(Request $request, CommerceExperimentService $service): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'variant_a_message' => 'required|string|max:1000',
            'variant_b_message' => 'required|string|max:1000',
        ]);

        $experiment = $service->createPromotionExperiment(
            $company,
            $validated['name'],
            ['message' => $validated['variant_a_message']],
            ['message' => $validated['variant_b_message']],
        );

        return response()->json(['experiment' => $experiment], 201);
    }

    public function evaluate(Request $request, CommerceExperiment $experiment, CommerceExperimentService $service): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company || $experiment->company_id !== $company->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $result = $service->evaluateWinner($experiment);

        return response()->json([
            'winner_id' => $result['winner_id'],
            'experiment' => $experiment->fresh()->load('variants'),
        ]);
    }
}
