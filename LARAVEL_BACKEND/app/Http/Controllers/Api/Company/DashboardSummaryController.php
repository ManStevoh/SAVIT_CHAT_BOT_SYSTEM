<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\CompanySetupStatusService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardSummaryController extends Controller
{
    public function __construct(
        protected CompanySetupStatusService $setup,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user      = $request->user();
        $company   = $user?->company;
        $companyId = $user?->company_id;

        if (! $companyId || ! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $period = $request->input('period', '7d');
        $days   = $period === '30d' ? 30 : ($period === '90d' ? 90 : 7);
        $since  = now()->subDays($days);

        // ── Analytics ─────────────────────────────────────────────────────────
        $hasAnalytics = PlanLimitService::companyHasAnalytics($company);
        $analytics    = null;

        if ($hasAnalytics) {
            $totalMessages  = Message::whereHas('chat', fn ($q) => $q->where('company_id', $companyId))
                ->where('created_at', '>=', $since)->count();
            $totalOrders    = Order::where('company_id', $companyId)->where('created_at', '>=', $since)->count();
            $totalRevenue   = (float) Order::where('company_id', $companyId)->where('created_at', '>=', $since)->sum('total');
            $totalCustomers = Order::where('company_id', $companyId)->where('created_at', '>=', $since)
                ->distinct('customer_phone')->count('customer_phone');

            $previousSince = now()->subDays($days * 2);
            $prevMessages  = Message::whereHas('chat', fn ($q) => $q->where('company_id', $companyId))
                ->whereBetween('created_at', [$previousSince, $since])->count();
            $prevOrders    = Order::where('company_id', $companyId)->whereBetween('created_at', [$previousSince, $since])->count();
            $prevRevenue   = (float) Order::where('company_id', $companyId)->whereBetween('created_at', [$previousSince, $since])->sum('total');
            $prevCustomers = Order::where('company_id', $companyId)->whereBetween('created_at', [$previousSince, $since])
                ->distinct('customer_phone')->count('customer_phone');

            $messagesPerDay = $this->seriesByDay(
                Message::whereHas('chat', fn ($q) => $q->where('company_id', $companyId))->where('created_at', '>=', $since),
                $days
            );
            $ordersPerDay = $this->seriesByDay(
                Order::where('company_id', $companyId)->where('created_at', '>=', $since),
                $days
            );

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
                ->map(fn ($p, $i) => ['id' => (string) ($i + 1), 'name' => $p->name, 'sales' => (int) $p->sales, 'revenue' => (float) $p->revenue])
                ->values()->all();

            if (empty($topProducts)) {
                $topProducts = Product::where('company_id', $companyId)->orderByDesc('created_at')->limit(4)->get()
                    ->map(fn ($p) => ['id' => (string) $p->id, 'name' => $p->name, 'sales' => 0, 'revenue' => 0.0])->all();
            }

            $analytics = [
                'totalMessages'   => $totalMessages,
                'totalOrders'     => $totalOrders,
                'totalRevenue'    => $totalRevenue,
                'totalCustomers'  => $totalCustomers,
                'messagesChange'  => $prevMessages  > 0 ? round((($totalMessages  - $prevMessages)  / $prevMessages)  * 100, 1) : 0,
                'ordersChange'    => $prevOrders    > 0 ? round((($totalOrders    - $prevOrders)    / $prevOrders)    * 100, 1) : 0,
                'revenueChange'   => $prevRevenue   > 0 ? round((($totalRevenue   - $prevRevenue)   / $prevRevenue)   * 100, 1) : 0,
                'customersChange' => $prevCustomers > 0 ? round((($totalCustomers - $prevCustomers) / $prevCustomers) * 100, 1) : 0,
                'messagesPerDay'  => $messagesPerDay,
                'ordersPerDay'    => $ordersPerDay,
                'topProducts'     => $topProducts,
            ];
        }

        // ── Recent Orders ─────────────────────────────────────────────────────
        $recentOrders = Order::where('company_id', $companyId)
            ->orderByDesc('created_at')->limit(5)->get()
            ->map(fn ($o) => [
                'id'           => (string) $o->id,
                'orderNumber'  => $o->order_number,
                'customerName' => $o->customer_name ?? $o->customer_phone ?? 'Unknown',
                'status'       => $o->status,
                'total'        => (float) $o->total,
                'products'     => $o->items ?? [],
                'createdAt'    => $o->created_at?->toIso8601String(),
            ])->values()->all();

        // ── Recent Chats ──────────────────────────────────────────────────────
        $recentChats = Chat::where('company_id', $companyId)
            ->orderByDesc('last_message_at')->limit(5)->get()
            ->map(fn ($c) => [
                'id'              => (string) $c->id,
                'customerName'    => $c->customer_name ?? $c->customer_phone ?? 'Unknown',
                'lastMessage'     => $c->last_message ?? '',
                'lastMessageTime' => $c->last_message_at?->diffForHumans() ?? '',
                'unreadCount'     => (int) ($c->unread_count ?? 0),
                'status'          => $c->status ?? 'active',
            ])->values()->all();

        // ── Subscription ──────────────────────────────────────────────────────
        $subscription = Subscription::where('company_id', $companyId)->orderByDesc('end_date')->first();
        $planModel    = $subscription
            ? Plan::where('slug', $subscription->plan)->first()
            : Plan::where('slug', 'starter')->first();

        if (! $subscription) {
            $subscriptionData = [
                'id' => '0', 'plan' => 'starter',
                'planName' => $planModel?->name ?? 'Starter',
                'status' => 'trial', 'daysRemaining' => 14, 'isExpiringSoon' => false,
            ];
        } else {
            $daysRemaining = (int) now()->startOfDay()->diffInDays($subscription->end_date->copy()->startOfDay(), false);
            $subscriptionData = [
                'id' => (string) $subscription->id,
                'plan' => $subscription->plan,
                'planName' => $planModel?->name ?? ucfirst((string) $subscription->plan),
                'status' => $subscription->status,
                'daysRemaining' => $daysRemaining,
                'isExpiringSoon' => $daysRemaining <= 7 && $daysRemaining >= 0,
            ];
        }

        // ── Settings (currency only, lightweight) ─────────────────────────────
        $companySettings = $company->settings()->first();
        $settingsData    = [
            'displayCurrency' => $companySettings?->display_currency ?? 'KES',
            'companyName'     => $company->name,
        ];

        // ── Setup Status ──────────────────────────────────────────────────────
        $setupStatus = $this->setup->status($company);

        return response()->json([
            'analytics'    => $analytics,
            'recentOrders' => $recentOrders,
            'recentChats'  => $recentChats,
            'subscription' => $subscriptionData,
            'settings'     => $settingsData,
            'setupStatus'  => $setupStatus,
            'period'       => $period,
        ]);
    }

    private function seriesByDay($query, int $days): array
    {
        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date     = now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd   = $date->copy()->endOfDay();
            $count    = (clone $query)->whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $result[] = ['date' => $labels[$dayStart->dayOfWeek], 'value' => $count];
        }
        if ($days <= 7) {
            return $result;
        }
        $byLabel = array_fill_keys($labels, 0);
        foreach ($result as $point) {
            $byLabel[$point['date']] += $point['value'];
        }
        return array_map(fn ($label) => ['date' => $label, 'value' => $byLabel[$label]], $labels);
    }
}
