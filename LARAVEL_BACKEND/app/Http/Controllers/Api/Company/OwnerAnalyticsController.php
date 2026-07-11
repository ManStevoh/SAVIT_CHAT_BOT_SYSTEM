<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\OwnerAnalyticsInvestigation;
use App\Services\Agent\Owner\OwnerAnalyticsAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerAnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $investigations = OwnerAnalyticsInvestigation::where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'question' => $inv->question,
                'period' => $inv->period,
                'status' => $inv->status,
                'confidence' => $inv->confidence,
                'findings' => $inv->findings,
                'recommendations' => $inv->recommendations,
                'createdAt' => $inv->created_at?->toIso8601String(),
            ]);

        return response()->json(['investigations' => $investigations]);
    }

    public function investigate(Request $request, OwnerAnalyticsAgentService $analytics): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'question' => 'required|string|min:5|max:500',
            'period' => 'nullable|string|in:7d,30d,90d',
        ]);

        $investigation = $analytics->investigate(
            $company,
            $validated['question'],
            $validated['period'] ?? '30d',
        );

        return response()->json([
            'investigation' => [
                'id' => $investigation->id,
                'question' => $investigation->question,
                'period' => $investigation->period,
                'status' => $investigation->status,
                'confidence' => $investigation->confidence,
                'evidence' => $investigation->evidence,
                'findings' => $investigation->findings,
                'recommendations' => $investigation->recommendations,
                'modelUsed' => $investigation->model_used,
                'createdAt' => $investigation->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
