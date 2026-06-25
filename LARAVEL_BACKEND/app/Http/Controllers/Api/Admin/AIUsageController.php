<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiRequestLog;
use App\Models\Company;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AIUsageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->input('period', '30d');
        $days = match ($period) {
            '24h' => 1,
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };
        $since = now()->subDays($days);
        $prevSince = now()->subDays($days * 2);

        $logsQuery = AiRequestLog::query()->where('created_at', '>=', $since);

        $totalRequests = (clone $logsQuery)->count();
        $successfulRequests = (clone $logsQuery)->where('success', true)->count();
        $totalTokens = (int) (clone $logsQuery)->sum('total_tokens');
        $totalCostUsd = round((float) (clone $logsQuery)->sum('estimated_cost_usd'), 4);
        $prevCost = round((float) AiRequestLog::whereBetween('created_at', [$prevSince, $since])->sum('estimated_cost_usd'), 4);
        $costChange = $prevCost > 0
            ? round((($totalCostUsd - $prevCost) / $prevCost) * 100, 1)
            : 0;
        $avgResponseTime = round(((clone $logsQuery)->avg('latency_ms') ?? 0) / 1000, 2);
        $successRate = $totalRequests > 0
            ? round(($successfulRequests / $totalRequests) * 100, 1)
            : 100.0;

        $prevRequests = AiRequestLog::whereBetween('created_at', [$prevSince, $since])->count();
        $prevTokens = (int) AiRequestLog::whereBetween('created_at', [$prevSince, $since])->sum('total_tokens');
        $requestsChange = $prevRequests > 0
            ? round((($totalRequests - $prevRequests) / $prevRequests) * 100, 1)
            : 0;
        $tokensChange = $prevTokens > 0
            ? round((($totalTokens - $prevTokens) / $prevTokens) * 100, 1)
            : 0;

        $usageByDay = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            $tokens = (int) AiRequestLog::whereBetween('created_at', [$dayStart, $dayEnd])->sum('total_tokens');
            $usageByDay[] = [
                'date' => $days <= 7 ? $dayStart->format('D') : $dayStart->format('M j'),
                'value' => $tokens,
            ];
        }

        $usageByCompany = AiRequestLog::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('company_id')
            ->select('company_id', DB::raw('COUNT(*) as requests'), DB::raw('SUM(total_tokens) as tokens'))
            ->groupBy('company_id')
            ->orderByDesc('tokens')
            ->limit(20)
            ->get();

        $companyIds = $usageByCompany->pluck('company_id')->all();
        $companies = Company::whereIn('id', $companyIds)->get()->keyBy('id');
        $usageByCompanyData = $usageByCompany->map(fn ($r) => [
            'companyId' => (string) $r->company_id,
            'companyName' => $companies->get($r->company_id)?->name ?? 'Unknown',
            'requests' => (int) $r->requests,
            'tokens' => (int) $r->tokens,
        ])->values()->all();

        $modelUsage = AiRequestLog::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('model')
            ->select('model', DB::raw('COUNT(*) as requests'), DB::raw('SUM(total_tokens) as tokens'))
            ->groupBy('model')
            ->orderByDesc('tokens')
            ->get()
            ->map(fn ($r) => [
                'model' => $r->model,
                'requests' => (int) $r->requests,
                'tokens' => (int) $r->tokens,
            ])
            ->values()
            ->all();

        $botRepliesBySource = Message::query()
            ->where('sender', 'bot')
            ->where('created_at', '>=', $since)
            ->whereNotNull('reply_source')
            ->select('reply_source', DB::raw('COUNT(*) as count'))
            ->groupBy('reply_source')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'source' => $r->reply_source,
                'count' => (int) $r->count,
            ])
            ->values()
            ->all();

        $usageByCredentialSource = AiRequestLog::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('credential_source')
            ->select(
                'credential_source',
                DB::raw('COUNT(*) as requests'),
                DB::raw('SUM(total_tokens) as tokens'),
                DB::raw('SUM(estimated_cost_usd) as estimated_cost_usd'),
                DB::raw('SUM(billed_cost_usd) as billed_cost_usd'),
            )
            ->groupBy('credential_source')
            ->get()
            ->map(fn ($r) => [
                'credentialSource' => $r->credential_source,
                'requests' => (int) $r->requests,
                'tokens' => (int) $r->tokens,
                'estimatedCostUsd' => round((float) $r->estimated_cost_usd, 4),
                'billedCostUsd' => round((float) $r->billed_cost_usd, 4),
            ])
            ->values()
            ->all();

        return response()->json([
            'totalRequests' => $totalRequests,
            'totalTokens' => $totalTokens,
            'totalCostUsd' => $totalCostUsd,
            'costChange' => $costChange,
            'avgResponseTime' => $avgResponseTime,
            'successRate' => $successRate,
            'requestsChange' => $requestsChange,
            'tokensChange' => $tokensChange,
            'usageByDay' => $usageByDay,
            'usageByCompany' => $usageByCompanyData,
            'modelUsage' => $modelUsage,
            'botRepliesBySource' => $botRepliesBySource,
            'usageByCredentialSource' => $usageByCredentialSource,
        ]);
    }
}
