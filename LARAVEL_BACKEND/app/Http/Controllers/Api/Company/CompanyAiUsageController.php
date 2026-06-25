<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\AiRequestLog;
use App\Services\AI\AiBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyAiUsageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $period = $request->input('period', '30d');
        $days = match ($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };
        $since = now()->subDays($days);

        $summary = app(AiBillingService::class)->usageSummary($company);

        $logsQuery = AiRequestLog::query()
            ->where('company_id', $company->id)
            ->where('created_at', '>=', $since);

        $byUseCase = (clone $logsQuery)
            ->select('use_case', DB::raw('COUNT(*) as requests'), DB::raw('SUM(total_tokens) as tokens'))
            ->groupBy('use_case')
            ->get()
            ->map(fn ($r) => [
                'useCase' => $r->use_case,
                'requests' => (int) $r->requests,
                'tokens' => (int) $r->tokens,
            ]);

        $byCredential = (clone $logsQuery)
            ->select('credential_source', DB::raw('COUNT(*) as requests'), DB::raw('SUM(billed_cost_usd) as cost'))
            ->groupBy('credential_source')
            ->get()
            ->map(fn ($r) => [
                'source' => $r->credential_source ?? 'platform',
                'requests' => (int) $r->requests,
                'billedCostUsd' => round((float) $r->cost, 4),
            ]);

        $learningCoverage = app(\App\Services\ConversationLearningService::class)
            ->embeddingCoveragePercent((int) $company->id);

        return response()->json([
            'summary' => $summary,
            'byUseCase' => $byUseCase,
            'byCredentialSource' => $byCredential,
            'learningEmbeddingCoveragePercent' => $learningCoverage,
        ]);
    }
}
