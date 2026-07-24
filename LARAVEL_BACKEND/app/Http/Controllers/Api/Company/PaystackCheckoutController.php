<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\PaystackService;
use App\Services\PaystackSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaystackCheckoutController extends Controller
{
    public function __construct(
        protected PaystackSubscriptionService $subscriptions
    ) {}

    /**
     * Initialize Paystack transaction for subscription. Returns authorization URL for redirect.
     */
    public function initialize(Request $request): JsonResponse
    {
        if (! PaystackService::isEnabled()) {
            return response()->json(['message' => 'Paystack is not enabled. Contact admin.'], 503);
        }

        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'planId' => 'required|string',
            'callbackUrl' => 'sometimes|nullable|url|max:500',
            'couponCode' => 'sometimes|nullable|string|max:64',
        ]);

        $plan = Plan::find($validated['planId']);
        if (! $plan) {
            return response()->json(['message' => 'Plan not found.'], 404);
        }

        $email = $company->email ?: $request->user()->email;
        if (! $email) {
            return response()->json(['message' => 'Company email is required for Paystack checkout.'], 422);
        }

        $result = $this->subscriptions->initializeCheckout(
            $company,
            $plan,
            $email,
            $validated['callbackUrl'] ?? null,
            $validated['couponCode'] ?? null,
        );

        if (! empty($result['error'])) {
            $message = $result['error'];
            $lower = strtolower($message);
            $status = str_contains($lower, 'callback')
                || str_contains($lower, 'not available')
                || str_contains($lower, 'coupon')
                || str_contains($lower, 'invalid')
                ? 422
                : 502;

            return response()->json(['message' => $message], $status);
        }

        return response()->json([
            'authorizationUrl' => $result['authorization_url'],
            'reference' => $result['reference'],
            'currency' => $result['currency'] ?? strtoupper(app(PaystackService::class)->getCurrency()),
            'amount' => $result['amount'] ?? null,
            'originalAmount' => $result['original_amount'] ?? null,
            'discountAmount' => $result['discount_amount'] ?? null,
            'coupon' => $result['coupon'] ?? null,
        ]);
    }

    /**
     * Verify Paystack payment after redirect callback (webhook fallback).
     */
    public function verify(Request $request): JsonResponse
    {
        if (! PaystackService::isEnabled()) {
            return response()->json(['message' => 'Paystack is not enabled. Contact admin.'], 503);
        }

        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'reference' => 'required|string|max:120',
        ]);

        $result = $this->subscriptions->verifyAndActivate($validated['reference'], $company);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Verification failed.',
            ], 422);
        }

        $subscription = $result['subscription'];

        return response()->json([
            'success' => true,
            'alreadyProcessed' => (bool) ($result['already_processed'] ?? false),
            'message' => ($result['already_processed'] ?? false)
                ? 'Subscription already active.'
                : 'Subscription activated.',
            'subscription' => [
                'id' => (string) $subscription->id,
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'startDate' => $subscription->start_date->format('Y-m-d'),
                'endDate' => $subscription->end_date->format('Y-m-d'),
                'amount' => (float) $subscription->amount,
                'paymentMethod' => $subscription->payment_method,
            ],
        ]);
    }
}
