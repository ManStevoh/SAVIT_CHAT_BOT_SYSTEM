<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\BusinessGraphNode;
use App\Services\Agent\Graph\BusinessGraphV2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessGraphController extends Controller
{
    public function show(Request $request, BusinessGraphV2Service $graph): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $limit = (int) $request->query('limit', 200);

        return response()->json($graph->exportGraph($company, min(500, max(10, $limit))));
    }

    public function sync(Request $request, BusinessGraphV2Service $graph): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }
