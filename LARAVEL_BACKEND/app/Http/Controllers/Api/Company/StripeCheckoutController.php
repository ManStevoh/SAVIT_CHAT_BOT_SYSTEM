<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeCheckoutController extends Controller
{
    public function __construct(
        protected StripeService $stripe
    ) {}

    /**
     * Create Stripe Checkout Session for the given plan. Returns { url } or error.
     */
    public function createSession(Request $request): JsonResponse
    {
        if (! StripeService::isEnabled()) {
            return response()->json(['message' => 'Stripe is not enabled. Contact admin.'], 503);
        }

        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'planId' => 'required|string',
            'successUrl' => 'nullable|string|url',
            'cancelUrl' => 'nullable|string|url',
        ]);

        $plan = Plan::find($validated['planId']);
        if (! $plan || ! $plan->stripe_price_id) {
            return response()->json(
                ['message' => 'Plan not found or not available for checkout. Add a Stripe Price ID in Admin → Plans.'],
                422
            );
        }

        $frontendUrl = rtrim(env('FRONTEND_URL', config('app.url')), '/');
        $successUrl = $validated['successUrl'] ?? $frontendUrl.'/dashboard/subscription?checkout=success';
        $cancelUrl = $validated['cancelUrl'] ?? $frontendUrl.'/dashboard/subscription?checkout=cancelled';

        $url = $this->stripe->createCheckoutSession($company, $plan, [
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        if (! $url) {
            return response()->json(
                ['message' => 'Unable to create checkout session. Please try again.'],
                502
            );
        }

        return response()->json(['url' => $url]);
    }

    /**
     * Create Stripe Billing Portal session. Returns { url } or error.
     */
    public function createPortalSession(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'returnUrl' => 'nullable|string|url',
        ]);

        $frontendUrl = rtrim(env('FRONTEND_URL', config('app.url')), '/');
        $returnUrl = $validated['returnUrl'] ?? $frontendUrl.'/dashboard/subscription';

        $url = $this->stripe->createBillingPortalSession($company, $returnUrl);

        if (! $url) {
            return response()->json(
                ['message' => 'No Stripe customer linked yet. Subscribe to a plan first.'],
                422
            );
        }

        return response()->json(['url' => $url]);
    }
}
