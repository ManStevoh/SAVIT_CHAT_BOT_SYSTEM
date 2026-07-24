<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (!$companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $company = $request->user()->company;
        if ($company && ! \App\Services\PlanLimitService::companyHasAnalytics($company)) {
            return response()->json([
                'message' => 'Analytics dashboard is available on Growth and Enterprise plans.',
                'code' => 'analytics_required',
                'upgradeUrl' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/dashboard/subscription',
            ], 403);
        }

        $period = $request->input('period', '7d');
        $days = $period === '30d' ? 30 : ($period === '90d' ? 90 : 7);
        $since = now()->subDays($days);

        $totalMessages = Message::whereHas('chat', fn ($q) => $q->where('company_id', $companyId))
            ->where('created_at', '>=', $since)->count();
        $totalOrders = Order::where('company_id', $companyId)->where('created_at', '>=', $since)->count();
        $totalRevenue = (float) Order::where('company_id', $companyId)->where('created_at', '>=', $since)->sum('total');
        $totalCustomers = Order::where('company_id', $companyId)->where('created_at', '>=', $since)
            ->distinct('customer_phone')->count('customer_phone');

        $previousSince = now()->subDays($days * 2);
        $prevMessages = Message::whereHas('chat', fn ($q) => $q->where('company_id', $companyId))
            ->whereBetween('created_at', [$previousSince, $since])->count();
        $prevOrders = Order::where('company_id', $companyId)->whereBetween('created_at', [$previousSince, $since])->count();
        $prevRevenue = (float) Order::where('company_id', $companyId)->whereBetween('created_at', [$previousSince, $since])->sum('total');
        $prevCustomers = Order::where('company_id', $companyId)->whereBetween('created_at', [$previousSince, $since])
            ->distinct('customer_phone')->count('customer_phone');

        $messagesChange = $prevMessages > 0 ? round((($totalMessages - $prevMessages) / $prevMessages) * 100, 1) : 0;
        $ordersChange = $prevOrders > 0 ? round((($totalOrders - $prevOrders) / $prevOrders) * 100, 1) : 0;
        $revenueChange = $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;
        $customersChange = $prevCustomers > 0 ? round((($totalCustomers - $prevCustomers) / $prevCustomers) * 100, 1) : 0;

        $messagesPerDay = $this->seriesByDay(Message::whereHas('chat', fn ($q) => $q->where('company_id', $companyId))
            ->where('created_at', '>=', $since), $days, 'messages');
        $ordersPerDay = $this->seriesByDay(Order::where('company_id', $companyId)->where('created_at', '>=', $since), $days, 'orders');
        $revenuePerDay = $this->revenueByDay($companyId, $days);

        $topProducts = DB::table('order_products')
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->where('orders.company_id', $companyId)
            ->where('orders.created_at', '>=', $since)
            ->select([
                'order_products.name as name',
                DB::raw('SUM(order_products.quantity) as sales'),
                DB::raw('SUM(order_products.quantity * order_products.price) as revenue'),
            ])
            ->groupBy('order_products.name')
            ->orderByDesc('sales')
            ->limit(10)
            ->get()
            ->map(fn ($p, $i) => [
                'id' => (string) ($i + 1),
                'name' => $p->name,
                'sales' => (int) $p->sales,
                'revenue' => (float) $p->revenue,
            ])->values()->all();

        if (empty($topProducts)) {
            $topProducts = Product::where('company_id', $companyId)->orderByDesc('created_at')->limit(4)->get()
                ->map(fn ($p) => ['id' => (string) $p->id, 'name' => $p->name, 'sales' => 0, 'revenue' => 0.0])->all();
        }

        $customerGrowth = $this->customerGrowthSeries($companyId, $days);

        return response()->json([
            'totalMessages' => $totalMessages,
            'totalOrders' => $totalOrders,
            'totalRevenue' => $totalRevenue,
            'totalCustomers' => $totalCustomers,
            'messagesChange' => $messagesChange,
            'ordersChange' => $ordersChange,
            'revenueChange' => $revenueChange,
            'customersChange' => $customersChange,
            'messagesPerDay' => $messagesPerDay,
            'ordersPerDay' => $ordersPerDay,
            'revenuePerDay' => $revenuePerDay,
            'topProducts' => $topProducts,
            'customerGrowth' => $customerGrowth,
        ]);
    }

    private function seriesByDay($query, int $days, string $type): array
    {
        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            $count = (clone $query)->whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $result[] = ['date' => $labels[$dayStart->dayOfWeek], 'value' => $count];
        }
        if ($days === 7) {
            return $result;
        }
        return $this->aggregateByLabel($result, $days, $labels);
    }

    private function aggregateByLabel(array $series, int $days, array $labels): array
    {
        if ($days <= 7) {
            return $series;
        }
        $byLabel = array_fill_keys($labels, 0);
        foreach ($series as $point) {
            $byLabel[$point['date']] = ($byLabel[$point['date']] ?? 0) + $point['value'];
        }
        return array_map(fn ($label) => ['date' => $label, 'value' => $byLabel[$label]], $labels);
    }

    private function revenueByDay(int $companyId, int $days): array
    {
        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            $sum = (float) Order::where('company_id', $companyId)->whereBetween('created_at', [$dayStart, $dayEnd])->sum('total');
            $result[] = ['date' => $labels[$dayStart->dayOfWeek], 'value' => $sum];
        }
        if ($days === 7) {
            return $result;
        }
        $byLabel = array_fill_keys($labels, 0.0);
        foreach ($result as $point) {
            $byLabel[$point['date']] += $point['value'];
        }
        return array_map(fn ($label) => ['date' => $label, 'value' => $byLabel[$label]], $labels);
    }

    private function customerGrowthSeries(int $companyId, int $days): array
    {
        $result = [];
        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        for ($i = min($days, 6) - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            $count = Order::where('company_id', $companyId)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->distinct('customer_phone')->count('customer_phone');
            $result[] = ['date' => $monthLabels[$monthStart->month - 1], 'value' => $count];
        }
        return $result;
    }
}
