<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\MailService;
use App\Services\OrderPaymentService;
use App\Services\Platform\BillingLedgerService;
use App\Services\SubscriptionLifecycleService;
use App\Services\SubscriptionPricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    public function __construct(
        protected MailService $mailService,
        protected OrderPaymentService $orderPaymentService,
        protected BillingLedgerService $billingLedger,
        protected SubscriptionPricingService $pricing,
        protected SubscriptionLifecycleService $lifecycle,
    ) {}

    /**
     * Daraja API calls this URL with STK push result. Validates pending cache and amount before marking paid.
     */
    public function __invoke(Request $request): Response
    {
        $payload = $request->all();

        $stkCallback = $payload['Body']['stkCallback'] ?? null;
        if (! $stkCallback) {
            Log::warning('M-Pesa callback: missing Body.stkCallback');

            return response('OK', 200);
        }

        $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;
        $resultCode = (int) ($stkCallback['ResultCode'] ?? -1);
        $resultDesc = $stkCallback['ResultDesc'] ?? '';

        if ($resultCode !== 0) {
            Log::info('M-Pesa callback: payment not successful', [
                'CheckoutRequestID' => $checkoutRequestId,
                'ResultCode' => $resultCode,
                'ResultDesc' => $resultDesc,
            ]);

            return response('OK', 200);
        }

        $paidAmount = $this->extractCallbackAmount($stkCallback);

        // Order payment: key mpesa_pending_order:{CheckoutRequestID}
        $pendingOrder = $checkoutRequestId ? Cache::get(OrderPaymentService::CACHE_KEY_ORDER_PREFIX.$checkoutRequestId) : null;
        if ($pendingOrder && ! empty($pendingOrder['order_id'])) {
            $order = Order::find($pendingOrder['order_id']);
            if ($order && $order->payment_status !== 'paid') {
                $expectedAmount = (float) ($pendingOrder['expected_amount'] ?? $order->total);
                if (! $this->amountsMatch($paidAmount, $expectedAmount)) {
                    Log::warning('M-Pesa callback: order amount mismatch', [
                        'CheckoutRequestID' => $checkoutRequestId,
                        'paid' => $paidAmount,
                        'expected' => $expectedAmount,
                        'order_id' => $order->id,
                    ]);

                    return response('OK', 200);
                }

                $this->orderPaymentService->markOrderPaid($order);
            }
            Cache::forget(OrderPaymentService::CACHE_KEY_ORDER_PREFIX.$checkoutRequestId);

            return response('OK', 200);
        }

        // Subscription payment: key mpesa_pending:{CheckoutRequestID}
        $pending = $checkoutRequestId ? Cache::get('mpesa_pending:'.$checkoutRequestId) : null;
        if (! $pending) {
            Log::warning('M-Pesa callback: no pending record for CheckoutRequestID '.$checkoutRequestId);

            return response('OK', 200);
        }

        $companyId = $pending['company_id'] ?? null;
        $planSlug = $pending['plan_slug'] ?? null;
        if (! $companyId || ! $planSlug) {
            return response('OK', 200);
        }

        $company = Company::find($companyId);
        $plan = Plan::where('slug', $planSlug)->first();
        if (! $company || ! $plan) {
            return response('OK', 200);
        }

        $expectedAmount = (float) ($pending['expected_amount'] ?? $plan->price_amount);
        $amount = $paidAmount > 0 ? $paidAmount : $expectedAmount;

        if (! $this->amountsMatch($amount, $expectedAmount)) {
            Log::warning('M-Pesa callback: subscription amount mismatch', [
                'CheckoutRequestID' => $checkoutRequestId,
                'paid' => $amount,
                'expected' => $expectedAmount,
            ]);

            return response('OK', 200);
        }

        $metadata = $stkCallback['CallbackMetadata']['Item'] ?? [];
        $transactionId = '';
        foreach ($metadata as $item) {
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                $transactionId = (string) ($item['Value'] ?? '');
            }
        }

        $startDate = now()->format('Y-m-d');
        $endDate = now()->addMonth()->format('Y-m-d');

        Subscription::where('company_id', $company->id)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'cancelled']);

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan->slug,
            'status' => 'active',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'amount' => $amount,
            'billing_cycle' => 'monthly',
            'payment_method' => 'mpesa',
            'external_payment_id' => $transactionId ?: ('mpesa_'.$checkoutRequestId),
        ]);

        $this->billingLedger->record(
            'mpesa',
            'mpesa_'.$checkoutRequestId,
            $amount,
            $company->id,
            $subscription->id,
            'KES',
            'subscription',
            $transactionId ?: null,
            [
                'plan' => $plan->slug,
                'coupon_code' => $pending['coupon_code'] ?? null,
                'discount_amount' => $pending['discount_amount'] ?? 0,
            ],
        );

        if ($checkoutRequestId) {
            $this->pricing->completeRedemption((string) $checkoutRequestId, $subscription->id);
        }

        Cache::forget('mpesa_pending:'.$checkoutRequestId);

        try {
            $planName = $plan->name;
            $endFormatted = now()->addMonth()->format('F j, Y');
            if ($company->email) {
                $this->mailService->sendSubscriptionConfirmed(
                    $company->email,
                    $planName,
                    $endFormatted
                );
            }
            $this->lifecycle->notifySubscriptionConfirmed($company, $planName, $endFormatted);
        } catch (\Throwable $e) {
            Log::warning('M-Pesa: failed to send subscription email: '.$e->getMessage());
        }

        try {
            app(\App\Services\Agent\AgentCommerceProvisioningService::class)->syncForCompany($company->fresh());
        } catch (\Throwable $e) {
            Log::warning('M-Pesa: agent commerce provision failed: '.$e->getMessage());
        }

        return response('OK', 200);
    }

    /**
     * @param  array<string, mixed>  $stkCallback
     */
    protected function extractCallbackAmount(array $stkCallback): float
    {
        foreach ($stkCallback['CallbackMetadata']['Item'] ?? [] as $item) {
            if (($item['Name'] ?? '') === 'Amount') {
                return (float) ($item['Value'] ?? 0);
            }
        }

        return 0.0;
    }

    protected function amountsMatch(float $paid, float $expected): bool
    {
        if ($expected <= 0) {
            return $paid > 0;
        }

        return abs($paid - $expected) <= 0.01;
    }
}
