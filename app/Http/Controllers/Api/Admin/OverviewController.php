<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $totalCompanies = Company::count();
        $activeCompanies = Company::where('status', 'active')->count();
        $totalUsers = User::count();
        $totalOrders = Order::count();
        $totalMessages = Message::whereHas('chat')->count();

        $totalRevenue = (float) Order::sum('total');

        $previousMonthStart = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();
        $currentMonthStart = now()->startOfMonth();

        $companiesPrevious = Company::whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->count();
        $companiesCurrent = Company::where('created_at', '>=', $currentMonthStart)->count();
        $companiesChange = $companiesPrevious > 0
            ? round((($companiesCurrent - $companiesPrevious) / $companiesPrevious) * 100, 1)
            : 0;

        $revenuePrevious = (float) Order::whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->sum('total');
        $revenueCurrent = (float) Order::where('created_at', '>=', $currentMonthStart)->sum('total');
        $revenueChange = $revenuePrevious > 0
            ? round((($revenueCurrent - $revenuePrevious) / $revenuePrevious) * 100, 1)
            : 0;

        $messagesPrevious = Message::whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->count();
        $messagesCurrent = Message::where('created_at', '>=', $currentMonthStart)->count();
        $messagesChange = $messagesPrevious > 0
            ? round((($messagesCurrent - $messagesPrevious) / $messagesPrevious) * 100, 1)
            : 0;

        return response()->json([
            'totalCompanies' => $totalCompanies,
            'activeCompanies' => $activeCompanies,
            'totalUsers' => $totalUsers,
            'totalRevenue' => $totalRevenue,
            'totalMessages' => $totalMessages,
            'totalOrders' => $totalOrders,
            'companiesChange' => $companiesChange,
            'revenueChange' => $revenueChange,
            'messagesChange' => $messagesChange,
        ]);
    }
}
