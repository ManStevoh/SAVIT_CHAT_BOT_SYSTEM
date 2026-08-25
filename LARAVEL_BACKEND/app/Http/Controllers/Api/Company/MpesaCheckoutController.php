<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\MpesaService;
use App\Services\SubscriptionPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MpesaCheckoutController extends Controller
{
    public function __construct(
        protected MpesaService $mpesa,
        protected SubscriptionPricingService $pricing,
    ) {}

    /**
     * Initiate M-Pesa STK push for subscription. Returns checkoutRequestId for polling; user completes payment on phone.
     */
    public function initiate(Request $request): JsonResponse
    {
        if (! MpesaService::isEnabled()) {
            return response()->json(['message' => 'M-Pesa is not enabled. Contact admin.'], 503);
        }

        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'planId' => 'required|string',
            'phone' => 'required|string|min:9',
            'couponCode' => 'sometimes|nullable|string|max:64',
        ]);

        $plan = Plan::find($validated['planId']);
        if (! $plan) {
            return response()->json(['message' => 'Plan not found.'], 404);
        }

        $quote = $this->pricing->quote($plan, $company, $validated['couponCode'] ?? null, 'KES');
        if (! ($quote['success'] ?? false)) {
            return response()->json(['message' => $quote['message'] ?? 'Could not price this plan.'], 422);
        }

        $amount = (float) $quote['final_amount'];
        if ($amount <= 0) {
            return response()->json(['message' => 'This plan is not available for M-Pesa payment.'], 422);
        }

        $config = PaymentGateway::getConfig('mpesa');
        $callbackUrl = $config['callback_url'] ?? '';
        if (! $callbackUrl) {
            return response()->json(['message' => 'M-Pesa callback URL not configured. Contact admin.'], 503);
        }

        $result = $this->mpesa->stkPush(
            $validated['phone'],
            $amount,
            'ESSEM_'.$plan->slug,
            'Subscription '.$plan->name,
            $callbackUrl
        );

        if (isset($result['error'])) {
            return response()->json([
                'message' => $result['error'],
                'checkoutRequestId' => null,
            ], 502);
        }

        $checkoutRequestId = $result['CheckoutRequestID'] ?? null;
        if (! $checkoutRequestId) {
            return response()->json([
                'message' => $result['ResponseDescription'] ?? 'STK push failed',
                'checkoutRequestId' => null,
            ], 502);
        }

        Cache::put('mpesa_pending:'.$checkoutRequestId, [
            'company_id' => $company->id,
            'plan_slug' => $plan->slug,
            'expected_amount' => $amount,
            'original_amount' => $quote['original_amount'] ?? $amount,
            'discount_amount' => $quote['discount_amount'] ?? 0,
            'coupon_code' => $quote['code'] ?? null,
        ], now()->addMinutes(10));

        if (! empty($quote['offer'])) {
            $this->pricing->reserveRedemption(
                $quote['offer'],
                $company,
                (string) $checkoutRequestId,
                (float) $quote['original_amount'],
                (float) $quote['discount_amount'],
                $amount,
                'KES'
            );
        }

        return response()->json([
            'checkoutRequestId' => $checkoutRequestId,
            'message' => 'Enter your M-Pesa PIN on your phone to complete payment.',
            'amount' => $amount,
            'originalAmount' => $quote['original_amount'] ?? $amount,
            'discountAmount' => $quote['discount_amount'] ?? 0,
            'coupon' => $quote['code'] ?? null,
        ]);
    }
}
