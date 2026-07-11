<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Agent\Memory\BusinessMemorySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemorySearchController extends Controller
{
    public function search(Request $request, BusinessMemorySearchService $search): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'query' => 'required|string|min:2|max:500',
            'limit' => 'sometimes|integer|min:1|max:30',
        ]);

        return response()->json($search->search(
            $company,
            $validated['query'],
            (int) ($validated['limit'] ?? 15),
        ));
    }
}
