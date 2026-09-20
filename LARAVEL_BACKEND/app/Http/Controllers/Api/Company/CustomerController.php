<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MailService;
use App\Services\OrderFulfillmentService;
use App\Support\PhoneSearch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $totalCustomers = Order::where('company_id', $companyId)
            ->distinct('customer_phone')
            ->count('customer_phone');

        $thisMonthStart = now()->startOfMonth();
        $newThisMonth = Order::where('company_id', $companyId)
            ->where('created_at', '>=', $thisMonthStart)
            ->distinct('customer_phone')
            ->count('customer_phone');

        $activeCustomers = Order::where('company_id', $companyId)
            ->where('updated_at', '>=', now()->subDays(30))
            ->distinct('customer_phone')
            ->count('customer_phone');

        $totalOrders = Order::where('company_id', $companyId)->count();
        $avgOrdersPerCustomer = $totalCustomers > 0 ? round($totalOrders / $totalCustomers, 1) : 0;

        return response()->json([
            'totalCustomers' => $totalCustomers,
            'newThisMonth' => $newThisMonth,
            'activeCustomers' => $activeCustomers,
            'avgOrdersPerCustomer' => $avgOrdersPerCustomer,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (!$companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $query = Order::where('company_id', $companyId)
            ->select([
                'customer_name as name',
                'customer_phone as phone',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(total), 0) as total_spent'),
                DB::raw('MAX(updated_at) as last_order_at'),
                DB::raw('MIN(created_at) as first_order_at'),
                DB::raw('MAX(NULLIF(customer_email, \'\')) as email'),
            ])
            ->groupBy('customer_name', 'customer_phone');

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $patterns = PhoneSearch::likePatterns($search);
            $query->where(function ($q) use ($search, $patterns) {
                $q->where('customer_name', 'like', "%{$search}%");
                foreach ($patterns as $pattern) {
                    $q->orWhere('customer_phone', 'like', $pattern);
                }
            });
        }

        $rows = $query->orderByDesc(DB::raw('MAX(updated_at)'))->get();
        $total = $rows->count();

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $totalPages = (int) ceil($total / $limit);
        $slice = $rows->slice(($page - 1) * $limit, $limit);

        $customers = $slice->values()->map(function ($row, $index) use ($page, $limit) {
            $email = strtolower(trim((string) ($row->email ?? '')));

            return [
                'id' => (string) (($page - 1) * $limit + $index + 1),
                'name' => $row->name,
                'phone' => $row->phone,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                'avatar' => null,
                'totalOrders' => (int) $row->total_orders,
                'totalSpent' => (float) $row->total_spent,
                'lastOrderDate' => $row->last_order_at ? Carbon::parse($row->last_order_at)->format('Y-m-d') : '',
                'createdAt' => $row->first_order_at ? Carbon::parse($row->first_order_at)->format('Y-m-d') : '',
            ];
        })->values()->all();

        return response()->json([
            'customers' => $customers,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    public function resendDigital(Request $request, OrderFulfillmentService $fulfillment): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'phone' => 'required|string|max:40',
            'customerEmail' => 'sometimes|nullable|email|max:255',
            'orderId' => 'sometimes|nullable',
        ]);

        $patterns = PhoneSearch::likePatterns($validated['phone']);
        $query = Order::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($validated, $patterns) {
                $q->where('customer_phone', $validated['phone']);
                foreach ($patterns as $pattern) {
                    $q->orWhere('customer_phone', 'like', $pattern);
                }
            })
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereNull('payment_status')->orWhere('payment_status', '!=', 'refunded');
            })
            ->with(['orderProducts.product', 'company.settings', 'company.whatsappAccount', 'chat'])
            ->orderByDesc('id');

        if (! empty($validated['orderId'])) {
            $query->whereKey($validated['orderId']);
        }

        $orders = $query->limit(20)->get();
        $mail = app(MailService::class);
        $digital = $orders->filter(fn (Order $order) => $mail->orderHasDigitalItems($order))->values();

        if ($digital->isEmpty()) {
            return response()->json([
                'success' => false,
                'emailSent' => false,
                'whatsappSent' => false,
                'pdfAttached' => false,
                'needsEmail' => false,
                'message' => 'This customer has no digital product to resend.',
            ], 422);
        }

        $paid = $digital->filter(
            fn (Order $order) => strtolower((string) $order->payment_status) === 'paid'
        );
        $targets = ($paid->isNotEmpty() ? $paid : $digital)->take(5);

        $sent = [];
        $anySuccess = false;
        $needsEmail = false;
        $emailSent = false;
        $whatsappSent = false;
        $pdfAttached = false;
        $lastMessage = 'Could not resend the digital files.';

        foreach ($targets as $order) {
            $result = $fulfillment->resendDigitalToCustomer($order, $validated['customerEmail'] ?? null);
            $sent[] = [
                'orderNumber' => $result['orderNumber'] ?? $order->order_number,
                'success' => (bool) ($result['success'] ?? false),
                'message' => $result['message'] ?? null,
            ];
            if ($result['success'] ?? false) {
                $anySuccess = true;
                $lastMessage = $result['message'] ?? $lastMessage;
            } elseif (! $anySuccess && isset($result['message'])) {
                $lastMessage = $result['message'];
            }
            $needsEmail = $needsEmail || (bool) ($result['needsEmail'] ?? false);
            $emailSent = $emailSent || (bool) ($result['emailSent'] ?? false);
            $whatsappSent = $whatsappSent || (bool) ($result['whatsappSent'] ?? false);
            $pdfAttached = $pdfAttached || (bool) ($result['pdfAttached'] ?? false);
        }

        if ($anySuccess && count($sent) > 1) {
            $numbers = collect($sent)->where('success', true)->pluck('orderNumber')->filter()->implode(', ');
            $lastMessage = 'Resent digital files for '.$numbers.'.';
            if ($pdfAttached) {
                $lastMessage .= ' The PDF is attached to the email.';
            }
        }

        return response()->json([
            'success' => $anySuccess,
            'emailSent' => $emailSent,
            'whatsappSent' => $whatsappSent,
            'pdfAttached' => $pdfAttached,
            'needsEmail' => $needsEmail && ! $anySuccess,
            'message' => $lastMessage,
            'orders' => $sent,
        ], $anySuccess ? 200 : 422);
    }
}
