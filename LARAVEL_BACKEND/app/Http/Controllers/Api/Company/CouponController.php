<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\SubscriptionPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        protected SubscriptionPricingService $pricing,
    ) {}

    /**
     * Preview a coupon against a plan (no redemption yet).
     */
    public function preview(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'planId' => 'required|string',
            'couponCode' => 'required|string|max:64',
            'currency' => 'nullable|string|size:3',
        ]);

        $plan = Plan::find($validated['planId']);
        if (! $plan) {
            return response()->json(['message' => 'Plan not found.'], 404);
        }

        $quote = $this->pricing->quote(
            $plan,
            $company,
            $validated['couponCode'],
            $validated['currency'] ?? null
        );

        if (! ($quote['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $quote['message'] ?? 'Invalid coupon.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'code' => $quote['code'],
            'originalAmount' => $quote['original_amount'],
            'discountAmount' => $quote['discount_amount'],
            'finalAmount' => $quote['final_amount'],
            'currency' => $quote['currency'],
            'message' => 'Coupon applied for checkout preview.',
        ]);
    }
}
