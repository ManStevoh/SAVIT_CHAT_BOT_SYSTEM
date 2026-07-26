<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $rates = TaxRate::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (TaxRate $rate) => $this->toArray($rate))
            ->values()
            ->all();

        return response()->json($rates);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $this->normalizeRequest($request);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'nullable|string|max:32',
            'rate' => 'required|numeric|min:0|max:100',
            'is_inclusive' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $rate = DB::transaction(function () use ($companyId, $validated) {
            $isDefault = (bool) ($validated['is_default'] ?? false);
            if ($isDefault) {
                TaxRate::query()
                    ->where('company_id', $companyId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return TaxRate::create([
                'company_id' => $companyId,
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'rate' => $validated['rate'],
                'is_inclusive' => (bool) ($validated['is_inclusive'] ?? false),
                'is_default' => $isDefault,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
        });

        return response()->json([
            'success' => true,
            'taxRate' => $this->toArray($rate),
            'message' => 'Tax rate created',
        ], 201);
    }

    public function update(Request $request, TaxRate $taxRate): JsonResponse
    {
        if ($taxRate->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $this->normalizeRequest($request);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'code' => 'nullable|string|max:32',
            'rate' => 'sometimes|numeric|min:0|max:100',
            'is_inclusive' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($taxRate, $validated) {
            if (array_key_exists('is_default', $validated) && $validated['is_default']) {
                TaxRate::query()
                    ->where('company_id', $taxRate->company_id)
                    ->where('id', '!=', $taxRate->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $updates = [];
            foreach (['name', 'code', 'rate'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updates[$field] = $validated[$field];
                }
            }
            foreach (['is_inclusive', 'is_default', 'is_active'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updates[$field] = (bool) $validated[$field];
                }
            }

            if ($updates !== []) {
                $taxRate->update($updates);
            }
        });

        return response()->json([
            'success' => true,
            'taxRate' => $this->toArray($taxRate->fresh()),
            'message' => 'Tax rate updated',
        ]);
    }

    public function destroy(Request $request, TaxRate $taxRate): JsonResponse
    {
        if ($taxRate->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $taxRate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tax rate deleted',
        ]);
    }

    private function normalizeRequest(Request $request): void
    {
        $map = [
            'isInclusive' => 'is_inclusive',
            'isDefault' => 'is_default',
            'isActive' => 'is_active',
        ];
        foreach ($map as $camel => $snake) {
            if ($request->has($camel) && ! $request->has($snake)) {
                $request->merge([$snake => $request->boolean($camel)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(TaxRate $rate): array
    {
        return [
            'id' => (string) $rate->id,
            'name' => $rate->name,
            'code' => $rate->code,
            'rate' => (float) $rate->rate,
            'isInclusive' => (bool) $rate->is_inclusive,
            'isDefault' => (bool) $rate->is_default,
            'isActive' => (bool) $rate->is_active,
            'createdAt' => optional($rate->created_at)->toIso8601String(),
            'updatedAt' => optional($rate->updated_at)->toIso8601String(),
        ];
    }
}
