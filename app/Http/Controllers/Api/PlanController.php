<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\Plan;
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

        $plans = Plan::orderBy('sort_order')->orderBy('id')->get();
        $data = $plans->map(function (Plan $p) use ($stripeEnabled, $mpesaEnabled) {
            $canStripe = $stripeEnabled && ! empty($p->stripe_price_id);
            $canMpesa = $mpesaEnabled && (float) $p->price_amount > 0 && ! $p->is_free;

            return [
                'id' => (string) $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => $p->price_display,
                'description' => $p->description ?? '',
                'features' => $p->features ?? [],
                'popular' => (bool) $p->popular,
                'cta' => $p->cta ?? 'Start Free Trial',
                'checkoutAvailable' => $canStripe || $canMpesa,
                'paymentMethods' => array_filter([
                    'stripe' => $canStripe,
                    'mpesa' => $canMpesa,
                ]),
            ];
        });

        return response()->json($data->values()->all());
    }
}
