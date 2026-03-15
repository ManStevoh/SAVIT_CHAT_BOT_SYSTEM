<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->input('period', '30d');
        $months = $period === '90d' ? 12 : ($period === '7d' ? 1 : 6);
        $since = now()->subMonths($months);

        $totalRevenue = (float) Order::where('created_at', '>=', $since)->sum('total');
        $mrr = (float) Subscription::where('status', 'active')->sum('amount');
        $arr = $mrr * 12;
        $prevSince = now()->subMonths($months * 2);
        $prevRevenue = (float) Order::whereBetween('created_at', [$prevSince, $since])->sum('total');
        $revenueChange = $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;

        $revenueByPlan = Subscription::where('status', 'active')
            ->select('plan', DB::raw('SUM(amount) as amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('plan')
            ->get()
            ->map(fn ($r) => [
                'plan' => ucfirst($r->plan),
                'amount' => (float) $r->amount,
                'count' => (int) $r->count,
            ])->values()->all();

        $revenueByMonth = [];
        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            $sum = (float) Order::whereBetween('created_at', [$monthStart, $monthEnd])->sum('total');
            $revenueByMonth[] = ['date' => $monthLabels[$monthStart->month - 1], 'value' => $sum];
        }

        $topCompanies = Order::where('created_at', '>=', $since)
            ->select('company_id', DB::raw('SUM(total) as revenue'))
            ->groupBy('company_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();
        $companyIds = $topCompanies->pluck('company_id')->all();
        $companies = Company::whereIn('id', $companyIds)->get()->keyBy('id');
        $topCompaniesData = $topCompanies->map(fn ($r) => [
            'id' => (string) $r->company_id,
            'name' => $companies->get($r->company_id)?->name ?? 'Unknown',
            'revenue' => (float) $r->revenue,
        ])->values()->all();

        return response()->json([
            'totalRevenue' => $totalRevenue,
            'mrr' => $mrr,
            'arr' => $arr,
            'revenueChange' => $revenueChange,
            'revenueByPlan' => $revenueByPlan,
            'revenueByMonth' => $revenueByMonth,
            'topCompanies' => $topCompaniesData,
        ]);
    }
}
