<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
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
            ])
            ->groupBy('customer_name', 'customer_phone');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc(DB::raw('MAX(updated_at)'))->get();
        $total = $rows->count();

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $totalPages = (int) ceil($total / $limit);
        $slice = $rows->slice(($page - 1) * $limit, $limit);

        $customers = $slice->values()->map(function ($row, $index) {
            return [
                'id' => (string) (($page - 1) * $limit + $index + 1),
                'name' => $row->name,
                'phone' => $row->phone,
                'email' => null,
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
}
