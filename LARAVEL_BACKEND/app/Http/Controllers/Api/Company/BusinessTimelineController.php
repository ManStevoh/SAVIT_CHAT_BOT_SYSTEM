<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Agent\Timeline\BusinessTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessTimelineController extends Controller
{
    public function index(Request $request, BusinessTimelineService $timeline): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'category' => 'sometimes|string|max:40',
        ]);

        return response()->json([
            'events' => $timeline->timeline(
                $company,
                (int) ($validated['limit'] ?? 50),
                $validated['category'] ?? null,
            ),
        ]);
    }

    public function sync(Request $request, BusinessTimelineService $timeline): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $count = $timeline->syncFromCompany($company);

        return response()->json(['synced' => $count], 201);
