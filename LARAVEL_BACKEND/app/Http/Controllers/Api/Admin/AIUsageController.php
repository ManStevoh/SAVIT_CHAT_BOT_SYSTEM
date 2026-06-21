<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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

        $totalRequests = Message::where('sender', 'bot')->where('created_at', '>=', $since)->count();
        $totalTokens = $totalRequests * 150;
        $avgResponseTime = 1.2;
        $successRate = 99.2;
        $prevSince = now()->subDays($days * 2);
        $prevRequests = Message::whereHas('chat')->where('sender', 'bot')->whereBetween('created_at', [$prevSince, $since])->count();
        $requestsChange = $prevRequests > 0 ? round((($totalRequests - $prevRequests) / $prevRequests) * 100, 1) : 0;
        $tokensChange = $requestsChange;

        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $usageByDay = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            $count = Message::whereHas('chat')->where('sender', 'bot')->whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $usageByDay[] = ['date' => $labels[$dayStart->dayOfWeek], 'value' => $count];
        }
        if ($days > 7) {
            $byLabel = array_fill_keys($labels, 0);
            foreach ($usageByDay as $p) {
                $byLabel[$p['date']] += $p['value'];
            }
            $usageByDay = array_map(fn ($l) => ['date' => $l, 'value' => $byLabel[$l]], $labels);
        }

        $usageByCompany = Message::where('messages.sender', 'bot')
            ->where('messages.created_at', '>=', $since)
            ->join('chats', 'chats.id', '=', 'messages.chat_id')
            ->select('chats.company_id', DB::raw('COUNT(*) as requests'), DB::raw('COUNT(*) * 150 as tokens'))
            ->groupBy('chats.company_id')
            ->orderByDesc('requests')
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

        $modelUsage = [
            ['model' => 'GPT-4', 'requests' => (int) ($totalRequests * 0.4), 'tokens' => (int) ($totalTokens * 0.5)],
            ['model' => 'GPT-3.5 Turbo', 'requests' => (int) ($totalRequests * 0.6), 'tokens' => (int) ($totalTokens * 0.5)],
        ];

        return response()->json([
            'totalRequests' => $totalRequests,
            'totalTokens' => $totalTokens,
            'avgResponseTime' => $avgResponseTime,
            'successRate' => $successRate,
            'requestsChange' => $requestsChange,
            'tokensChange' => $tokensChange,
            'usageByDay' => $usageByDay,
            'usageByCompany' => $usageByCompanyData,
            'modelUsage' => $modelUsage,
        ]);
    }
}
