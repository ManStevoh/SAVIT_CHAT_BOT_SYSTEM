<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaystackCheckoutController extends Controller
{
    public function __construct(
        protected PaystackService $paystack
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
        ]);

        $plan = Plan::find($validated['planId']);
        if (! $plan) {
            return response()->json(['message' => 'Plan not found.'], 404);
        }

        $amount = (float) $plan->price_amount;
        if ($amount <= 0 || $plan->is_free) {
            return response()->json(['message' => 'This plan is not available for Paystack payment.'], 422);
        }

        $email = $company->email ?: $request->user()->email;
        if (! $email) {
            return response()->json(['message' => 'Company email is required for Paystack checkout.'], 422);
        }

        $reference = 'essem_sub_'.$company->id.'_'.uniqid();
        $callbackUrl = $validated['callbackUrl']
            ?? config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')).'/dashboard/subscription?checkout=success';

        $result = $this->paystack->initializeTransaction(
            $email,
            $this->paystack->amountToSubunit($amount),
            $reference,
            $callbackUrl,
            [
                'company_id' => $company->id,
                'plan_slug' => $plan->slug,
                'type' => 'subscription',
            ]
        );

        if (! empty($result['error']) || empty($result['authorization_url'])) {
            return response()->json([
                'message' => $result['error'] ?? 'Paystack checkout failed',
            ], 502);
        }

        Cache::put(PaystackService::CACHE_KEY_SUB_PREFIX.$reference, [
            'company_id' => $company->id,
            'plan_slug' => $plan->slug,
        ], now()->addMinutes(30));

        return response()->json([
            'authorizationUrl' => $result['authorization_url'],
            'reference' => $result['reference'] ?? $reference,
        ]);
    }
}
