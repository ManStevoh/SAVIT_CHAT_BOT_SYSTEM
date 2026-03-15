<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    /**
     * List plans for public pricing page (no auth).
     */
    public function index(): JsonResponse
    {
        $plans = Plan::orderBy('sort_order')->orderBy('id')->get();
        $data = $plans->map(fn (Plan $p) => [
            'id' => (string) $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'price' => $p->price_display,
            'description' => $p->description ?? '',
            'features' => $p->features ?? [],
            'popular' => (bool) $p->popular,
            'cta' => $p->cta ?? 'Start Free Trial',
            'checkoutAvailable' => ! empty($p->stripe_price_id),
        ]);

        return response()->json($data->values()->all());
    }
}
