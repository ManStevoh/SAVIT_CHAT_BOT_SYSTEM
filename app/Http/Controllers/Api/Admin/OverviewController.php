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

        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();
        $previousMonthStart = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();

        $monthlyRevenue = (float) Order::whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])->sum('total');
        $totalRevenue = (float) Order::sum('total');

        $companiesPrevious = Company::whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->count();
        $companiesCurrent = Company::where('created_at', '>=', $currentMonthStart)->count();
        $companiesChange = $companiesPrevious > 0
            ? round((($companiesCurrent - $companiesPrevious) / $companiesPrevious) * 100, 1)
            : 0;

        $revenuePrevious = (float) Order::whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->sum('total');
        $revenueCurrent = $monthlyRevenue;
        $revenueChange = $revenuePrevious > 0
            ? round((($revenueCurrent - $revenuePrevious) / $revenuePrevious) * 100, 1)
            : 0;

        $messagesPrevious = Message::whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->count();
        $messagesCurrent = Message::where('created_at', '>=', $currentMonthStart)->count();
        $messagesChange = $messagesPrevious > 0
            ? round((($messagesCurrent - $messagesPrevious) / $messagesPrevious) * 100, 1)
            : 0;

        $usersPrevious = User::whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->count();
        $usersCurrent = User::where('created_at', '>=', $currentMonthStart)->count();
        $usersChange = $usersPrevious > 0
            ? round((($usersCurrent - $usersPrevious) / $usersPrevious) * 100, 1)
            : 0;

        $companyGrowthData = $this->buildCompanyGrowthData();
        $messageVolumeData = $this->buildMessageVolumeData();

        return response()->json([
            'totalCompanies' => $totalCompanies,
            'activeCompanies' => $activeCompanies,
            'totalUsers' => $totalUsers,
            'totalRevenue' => $totalRevenue,
            'monthlyRevenue' => $monthlyRevenue,
            'totalMessages' => $totalMessages,
            'totalOrders' => $totalOrders,
            'companiesChange' => $companiesChange,
            'revenueChange' => $revenueChange,
            'messagesChange' => $messagesChange,
            'usersChange' => $usersChange,
            'companyGrowthData' => $companyGrowthData,
            'messageVolumeData' => $messageVolumeData,
        ]);
    }

    /** Last 7 months: cumulative companies by month label. */
    private function buildCompanyGrowthData(): array
    {
        $months = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = [
                'name' => $date->format('M'),
                'companies' => Company::where('created_at', '<=', $date->endOfMonth())->count(),
            ];
        }
        return $months;
    }

    /** Last 7 days: message count per day. */
    private function buildMessageVolumeData(): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            $days[] = [
                'name' => $date->format('D'),
                'messages' => Message::whereHas('chat')->whereBetween('created_at', [$dayStart, $dayEnd])->count(),
            ];
        }
        return $days;
    }
}
