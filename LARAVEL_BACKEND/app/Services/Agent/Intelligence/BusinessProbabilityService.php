<?php

namespace App\Services\Agent\Intelligence;

use App\Models\BusinessProbabilityScore;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;

/**
 * ABI Level 4 — calibrated buy / churn / refund probability heuristics.
 */
final class BusinessProbabilityService
{
    /**
     * @return array{buy: float, churn: float, refund: float, factors: array<string, mixed>}
     */
    public function computeForCompany(Company $company): array
    {
        $companyId = (int) $company->id;
        $since30 = now()->subDays(30);
        $since90 = now()->subDays(90);

        $paidOrders = Order::where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $since90);

        $orderCount90 = (int) (clone $paidOrders)->count();
        $orderCount30 = (int) Order::where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $since30)
            ->count();

        $uniqueBuyers90 = (int) (clone $paidOrders)->distinct('customer_phone')->count('customer_phone');
        $repeatBuyers = (int) Order::where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $since90)
            ->selectRaw('customer_phone, COUNT(*) as c')
            ->groupBy('customer_phone')
            ->having('c', '>', 1)
            ->get()
            ->count();

        $refundCount = (int) Order::where('company_id', $companyId)
            ->where('payment_status', 'refunded')
            ->where('updated_at', '>=', $since90)
            ->count();

        $activeChats = (int) Chat::where('company_id', $companyId)
            ->where('updated_at', '>=', $since30)
            ->count();

        $repeatRate = $uniqueBuyers90 > 0 ? $repeatBuyers / $uniqueBuyers90 : 0.0;
        $velocity = min(1.0, $orderCount30 / max(1, $orderCount90) * 1.5);
