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
        $refundRate = $orderCount90 > 0 ? $refundCount / $orderCount90 : 0.05;
        $engagement = min(1.0, $activeChats / max(5, $uniqueBuyers90));

        $buy = $this->clamp(0.25 + ($repeatRate * 0.35) + ($velocity * 0.25) + ($engagement * 0.15));
        $churn = $this->clamp(0.65 - ($repeatRate * 0.3) - ($engagement * 0.25) - ($velocity * 0.1));
        $refund = $this->clamp(0.08 + ($refundRate * 0.7));

        $factors = [
            'order_count_30d' => $orderCount30,
            'order_count_90d' => $orderCount90,
            'repeat_buyer_rate' => round($repeatRate, 3),
            'refund_rate_90d' => round($refundRate, 3),
            'active_chats_30d' => $activeChats,
        ];

        $this->persistScore($companyId, null, 'buy', $buy, $factors);
        $this->persistScore($companyId, null, 'churn', $churn, $factors);
        $this->persistScore($companyId, null, 'refund', $refund, $factors);

        return [
            'buy' => round($buy, 4),
            'churn' => round($churn, 4),
            'refund' => round($refund, 4),
            'factors' => $factors,
        ];
    }

    /**
     * @param  array<string, mixed>  $factors
     */
    private function persistScore(int $companyId, ?string $phone, string $type, float $probability, array $factors): void
    {
        BusinessProbabilityScore::create([
            'company_id' => $companyId,
            'customer_phone' => $phone,
            'score_type' => $type,
            'probability' => $probability,
            'factors' => $factors,
            'computed_at' => now(),
        ]);
    }

    private function clamp(float $value): float
    {
        return max(0.05, min(0.95, $value));
    }
}
