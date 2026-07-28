<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryZoneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $zones = DeliveryZone::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (DeliveryZone $zone) => $this->toArray($zone))
            ->values()
            ->all();

        return response()->json($zones);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $this->validatePayload($request);

        $zone = DeliveryZone::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'fee' => $validated['fee'],
            'min_order_amount' => $validated['minOrderAmount'] ?? null,
            'keywords' => $this->normalizeKeywords($validated['keywords'] ?? null),
            'is_active' => $validated['isActive'] ?? true,
            'sort_order' => $validated['sortOrder'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'zone' => $this->toArray($zone),
            'message' => 'Delivery zone created',
        ], 201);
    }

    public function show(Request $request, DeliveryZone $deliveryZone): JsonResponse
    {
        if ($deliveryZone->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        return response()->json($this->toArray($deliveryZone));
    }

    public function update(Request $request, DeliveryZone $deliveryZone): JsonResponse
    {
        if ($deliveryZone->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $this->validatePayload($request, true);

        $updates = [];
        if (array_key_exists('name', $validated)) {
            $updates['name'] = $validated['name'];
        }
        if (array_key_exists('fee', $validated)) {
            $updates['fee'] = $validated['fee'];
        }
        if (array_key_exists('minOrderAmount', $validated)) {
            $updates['min_order_amount'] = $validated['minOrderAmount'];
        }
        if (array_key_exists('keywords', $validated)) {
            $updates['keywords'] = $this->normalizeKeywords($validated['keywords']);
        }
        if (array_key_exists('isActive', $validated)) {
            $updates['is_active'] = (bool) $validated['isActive'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $updates['sort_order'] = (int) $validated['sortOrder'];
        }

        if ($updates !== []) {
            $deliveryZone->update($updates);
        }

        return response()->json([
            'success' => true,
            'zone' => $this->toArray($deliveryZone->fresh()),
            'message' => 'Delivery zone updated',
        ]);
    }

    public function destroy(Request $request, DeliveryZone $deliveryZone): JsonResponse
    {
        if ($deliveryZone->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $deliveryZone->delete();

        return response()->json(['success' => true, 'message' => 'Delivery zone deleted']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $isUpdate = false): array
    {
        $rule = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'name' => "{$rule}|string|max:255",
            'fee' => "{$rule}|numeric|min:0",
            'minOrderAmount' => 'nullable|numeric|min:0',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:255',
            'isActive' => 'sometimes|boolean',
            'sortOrder' => 'sometimes|integer|min:0',
        ]);
    }

    /**
     * @return list<string>|null
     */
    private function normalizeKeywords(mixed $keywords): ?array
    {
        if (! is_array($keywords)) {
            return null;
        }

        $normalized = array_values(array_filter(
            array_map(fn ($k) => trim((string) $k), $keywords),
            fn ($k) => $k !== ''
        ));

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(DeliveryZone $zone): array
    {
        return [
            'id' => (string) $zone->id,
            'name' => $zone->name,
            'fee' => (float) $zone->fee,
            'minOrderAmount' => $zone->min_order_amount !== null ? (float) $zone->min_order_amount : null,
            'keywords' => is_array($zone->keywords) ? $zone->keywords : [],
            'isActive' => (bool) $zone->is_active,
            'sortOrder' => (int) $zone->sort_order,
            'createdAt' => optional($zone->created_at)->toIso8601String(),
            'updatedAt' => optional($zone->updated_at)->toIso8601String(),
        ];
    }
}
