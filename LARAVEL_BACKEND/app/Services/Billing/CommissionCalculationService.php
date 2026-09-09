<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderCommission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionCalculationService
{
    public function __construct(
        protected BillingResolutionService $resolver
    ) {}

    /**
     * Record commission for a paid order if the company is on commission billing.
     */
    public function recordOrderCommission(Order $order): ?OrderCommission
    {
        $company = $order->company;
        if (! $company) {
            return null;
        }

        // Avoid duplicate commission records for the same order
        $existing = OrderCommission::where('order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        $config = $this->resolver->resolve($company);
        if (! $config['is_commission_active'] || $config['rate'] <= 0) {
            return null;
        }

        // Calculate order base amount
        $orderTotal = (float) $order->total;
        if ($config['basis'] === 'subtotal' && isset($order->subtotal)) {
            $orderTotal = (float) $order->subtotal;
        }

        if ($orderTotal <= 0) {
            return null;
        }

        $rate = $config['rate'];
        $commissionAmount = round(($orderTotal * $rate) / 100, 2);

        // Determine collection mode based on payment method
        $paymentMethod = strtolower((string) ($order->payment_method ?? 'manual'));
        $isAutomaticGateway = in_array($paymentMethod, ['stripe_split', 'paystack_split', 'flutterwave_split'], true);

        $collectionMode = $isAutomaticGateway ? 'automatic_split' : 'manual_direct';
        $settlementStatus = $isAutomaticGateway ? 'settled' : 'accrued';
        $settledAt = $isAutomaticGateway ? now() : null;

        return DB::transaction(function () use (
            $company,
            $order,
            $orderTotal,
            $rate,
            $commissionAmount,
            $paymentMethod,
            $collectionMode,
            $settlementStatus,
            $settledAt
        ) {
            $commission = OrderCommission::create([
                'company_id'        => $company->id,
                'order_id'          => $order->id,
                'order_total'       => $orderTotal,
                'commission_rate'   => $rate,
                'commission_amount' => $commissionAmount,
                'payment_method'    => $paymentMethod,
                'collection_mode'   => $collectionMode,
                'settlement_status' => $settlementStatus,
                'settled_at'        => $settledAt,
            ]);

            // If manual collection, increment company's commission_balance_due
            if ($collectionMode === 'manual_direct') {
                $company->increment('commission_balance_due', $commissionAmount);
            }

            Log::info("Commission recorded for Order #{$order->order_number}: KES {$commissionAmount} ({$rate}%) [{$collectionMode}]");

            return $commission;
        });
    }
}
