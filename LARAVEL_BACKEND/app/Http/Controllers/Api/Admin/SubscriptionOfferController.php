<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SubscriptionOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionOfferController extends Controller
{
    public function index(): JsonResponse
    {
        $offers = SubscriptionOffer::with('plan:id,name,slug')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SubscriptionOffer $o) => $this->toArray($o));

        return response()->json($offers->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $offer = SubscriptionOffer::create($this->map($validated));

        return response()->json([
            'success' => true,
            'offer' => $this->toArray($offer->load('plan:id,name,slug')),
        ], 201);
    }

    public function update(Request $request, SubscriptionOffer $offer): JsonResponse
    {
        $validated = $this->validated($request, $offer->id);
        $offer->update($this->map($validated));

        return response()->json([
            'success' => true,
            'offer' => $this->toArray($offer->fresh()->load('plan:id,name,slug')),
        ]);
    }

    public function destroy(SubscriptionOffer $offer): JsonResponse
    {
        $offer->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('subscription_offers', 'code')->ignore($ignoreId),
            ],
            'description' => 'nullable|string|max:500',
            'discountType' => 'required|in:percent,fixed',
            'discountValue' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'planId' => 'nullable|integer|exists:plans,id',
            'maxRedemptions' => 'nullable|integer|min:1',
            'maxPerCompany' => 'nullable|integer|min:1|max:100',
            'startsAt' => 'nullable|date',
            'endsAt' => 'nullable|date|after_or_equal:startsAt',
            'isActive' => 'sometimes|boolean',
            'firstPaymentOnly' => 'sometimes|boolean',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function map(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'description' => $validated['description'] ?? null,
            'discount_type' => $validated['discountType'],
            'discount_value' => $validated['discountValue'],
            'currency' => isset($validated['currency']) ? strtoupper($validated['currency']) : null,
            'plan_id' => $validated['planId'] ?? null,
            'max_redemptions' => $validated['maxRedemptions'] ?? null,
            'max_per_company' => $validated['maxPerCompany'] ?? 1,
            'starts_at' => $validated['startsAt'] ?? null,
            'ends_at' => $validated['endsAt'] ?? null,
            'is_active' => $validated['isActive'] ?? true,
            'first_payment_only' => $validated['firstPaymentOnly'] ?? true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(SubscriptionOffer $o): array
    {
        return [
            'id' => (string) $o->id,
            'name' => $o->name,
            'code' => $o->code,
            'description' => $o->description,
            'discountType' => $o->discount_type,
            'discountValue' => (float) $o->discount_value,
            'currency' => $o->currency,
            'planId' => $o->plan_id ? (string) $o->plan_id : null,
            'planName' => $o->plan?->name,
            'maxRedemptions' => $o->max_redemptions,
            'redemptionCount' => (int) $o->redemption_count,
            'maxPerCompany' => (int) $o->max_per_company,
            'startsAt' => $o->starts_at?->toIso8601String(),
            'endsAt' => $o->ends_at?->toIso8601String(),
            'isActive' => (bool) $o->is_active,
            'firstPaymentOnly' => (bool) $o->first_payment_only,
            'isCurrentlyValid' => $o->isCurrentlyValid(),
        ];
    }
}
