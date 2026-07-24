<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\Platform\EntitlementService;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    /**
     * List plans for public pricing page (no auth).
     */
    public function index(): JsonResponse
    {
        $stripeEnabled = PaymentGateway::isEnabled('stripe');
        $mpesaEnabled = PaymentGateway::isEnabled('mpesa');
        $paystackEnabled = PaymentGateway::isEnabled('paystack');
        $paystackCurrency = $paystackEnabled
            ? strtoupper((string) (PaymentGateway::getConfig('paystack')['currency'] ?? 'NGN'))
            : null;

        $plans = Plan::orderBy('sort_order')->orderBy('id')->get();
        $data = $plans->map(function (Plan $p) use ($stripeEnabled, $mpesaEnabled, $paystackEnabled, $paystackCurrency) {
            $canStripe = $stripeEnabled && ! empty($p->stripe_price_id);
            $canMpesa = $mpesaEnabled && (float) $p->price_amount > 0 && ! $p->is_free;
            $canPaystack = $paystackEnabled && (float) $p->price_amount > 0 && ! $p->is_free;
            $limits = app(EntitlementService::class)->limitsForPlanSlug($p->slug);

            return [
                'id' => (string) $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => $p->price_display,
                'priceAmount' => $p->price_amount !== null ? (float) $p->price_amount : null,
                'paystackCurrency' => $canPaystack ? $paystackCurrency : null,
                'description' => $p->description ?? '',
                'features' => $p->features ?? [],
                'entitlements' => [
                    'messages' => $limits['messages'],
                    'team' => $limits['team'] ?? null,
                    'whatsappNumbers' => $limits['whatsapp_numbers'] ?? 1,
                    'apiAccess' => (bool) ($limits['api_access'] ?? false),
                    'analytics' => (bool) ($limits['analytics'] ?? false),
                    'aiPostsPerMonth' => $limits['ai_posts_per_month'] ?? null,
                    'socialPlatforms' => $limits['social_platforms'] ?? null,
                    'allowPhysical' => (bool) ($limits['allow_physical'] ?? true),
                    'allowDigital' => (bool) ($limits['allow_digital'] ?? true),
                    'allowService' => (bool) ($limits['allow_service'] ?? false),
                    'allowBookings' => (bool) ($limits['allow_bookings'] ?? false),
                    'maxBookingsPerMonth' => array_key_exists('max_bookings_per_month', $limits)
                        ? ($limits['max_bookings_per_month'] === null ? null : (int) $limits['max_bookings_per_month'])
                        : 0,
                ],
                'popular' => (bool) $p->popular,
                'cta' => $p->cta ?? 'Start Free Trial',
                'hasTrial' => (bool) $p->has_trial,
                'trialDays' => $p->trial_days !== null ? (int) $p->trial_days : null,
                'isFree' => (bool) $p->is_free,
                'checkoutAvailable' => $canStripe || $canMpesa || $canPaystack,
                'paymentMethods' => array_filter([
                    'stripe' => $canStripe,
                    'mpesa' => $canMpesa,
                    'paystack' => $canPaystack,
                ]),
            ];
        });

        return response()->json($data->values()->all());
    }
}
