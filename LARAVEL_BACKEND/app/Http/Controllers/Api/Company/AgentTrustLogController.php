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

        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        $logs = AgentTrustLog::where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AgentTrustLog $log) => [
                'id' => $log->id,
                'actionType' => $log->action_type,
                'goal' => $log->goal,
                'reasoningSummary' => $log->reasoning_summary,
                'confidence' => $log->confidence !== null ? (float) $log->confidence : null,
