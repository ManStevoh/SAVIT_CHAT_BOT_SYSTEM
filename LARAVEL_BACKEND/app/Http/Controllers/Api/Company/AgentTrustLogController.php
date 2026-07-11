<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\AgentTrustLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentTrustLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

