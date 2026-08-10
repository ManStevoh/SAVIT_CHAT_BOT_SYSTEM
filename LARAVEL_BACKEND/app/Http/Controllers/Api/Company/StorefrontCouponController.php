<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\StorefrontCoupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StorefrontCouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $coupons = StorefrontCoupon::where('company_id', $companyId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (StorefrontCoupon $c) => $this->toArray($c))
            ->values()
            ->all();

        return response()->json($coupons);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('storefront_coupons', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'type' => 'required|string|in:percent,fixed',
            'value' => 'required|numeric|min:0',
            'minOrder' => 'nullable|numeric|min:0',
            'maxRedemptions' => 'nullable|integer|min:1',
            'startsAt' => 'nullable|date',
            'endsAt' => 'nullable|date|after_or_equal:startsAt',
            'isActive' => 'nullable|boolean',
        ]);

        $coupon = StorefrontCoupon::create([
            'company_id' => $companyId,
            'code' => strtoupper(trim($validated['code'])),
            'type' => $validated['type'],
            'value' => $validated['value'],
            'min_order' => $validated['minOrder'] ?? null,
            'max_redemptions' => $validated['maxRedemptions'] ?? null,
            'starts_at' => $validated['startsAt'] ?? null,
            'ends_at' => $validated['endsAt'] ?? null,
            'is_active' => $validated['isActive'] ?? true,
            'redeemed_count' => 0,
        ]);

        return response()->json(['success' => true, 'coupon' => $this->toArray($coupon)], 201);
    }

    public function update(Request $request, StorefrontCoupon $storefrontCoupon): JsonResponse
    {
        if ($storefrontCoupon->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $companyId = (int) $request->user()->company_id;
        $validated = $request->validate([
            'code' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('storefront_coupons', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($storefrontCoupon->id),
            ],
            'type' => 'sometimes|string|in:percent,fixed',
            'value' => 'sometimes|numeric|min:0',
            'minOrder' => 'nullable|numeric|min:0',
            'maxRedemptions' => 'nullable|integer|min:1',
            'startsAt' => 'nullable|date',
            'endsAt' => 'nullable|date|after_or_equal:startsAt',
            'isActive' => 'nullable|boolean',
        ]);

        $updates = [];
        if (array_key_exists('code', $validated)) {
            $updates['code'] = strtoupper(trim((string) $validated['code']));
        }
        if (array_key_exists('type', $validated)) {
            $updates['type'] = $validated['type'];
        }
        if (array_key_exists('value', $validated)) {
            $updates['value'] = $validated['value'];
        }
        if (array_key_exists('minOrder', $validated)) {
            $updates['min_order'] = $validated['minOrder'];
        }
        if (array_key_exists('maxRedemptions', $validated)) {
            $updates['max_redemptions'] = $validated['maxRedemptions'];
        }
        if (array_key_exists('startsAt', $validated)) {
            $updates['starts_at'] = $validated['startsAt'];
        }
        if (array_key_exists('endsAt', $validated)) {
            $updates['ends_at'] = $validated['endsAt'];
        }
        if (array_key_exists('isActive', $validated)) {
            $updates['is_active'] = (bool) $validated['isActive'];
        }

        $storefrontCoupon->update($updates);

        return response()->json(['success' => true, 'coupon' => $this->toArray($storefrontCoupon->fresh())]);
    }

    public function destroy(Request $request, StorefrontCoupon $storefrontCoupon): JsonResponse
    {
        if ($storefrontCoupon->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $storefrontCoupon->delete();

        return response()->json(['success' => true]);
    }

    /** @return array<string, mixed> */
    private function toArray(StorefrontCoupon $coupon): array
    {
        return [
            'id' => (string) $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => (float) $coupon->value,
            'minOrder' => $coupon->min_order !== null ? (float) $coupon->min_order : null,
            'maxRedemptions' => $coupon->max_redemptions,
            'redeemedCount' => (int) $coupon->redeemed_count,
            'startsAt' => $coupon->starts_at?->toIso8601String(),
            'endsAt' => $coupon->ends_at?->toIso8601String(),
            'isActive' => (bool) $coupon->is_active,
            'isCurrentlyValid' => $coupon->isCurrentlyValid(),
        ];
    }
}
