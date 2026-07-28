<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\DineInTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DineInTableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        $tables = DineInTable::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get()
            ->map(fn (DineInTable $t) => $this->serialize($t));

        return response()->json(['tables' => $tables]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'nullable|string|max:40',
            'seats' => 'nullable|integer|min:1|max:100',
            'isActive' => 'sometimes|boolean',
        ]);

        $table = DineInTable::create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'seats' => $validated['seats'] ?? null,
            'is_active' => $validated['isActive'] ?? true,
        ]);
        $table->load('company');

        return response()->json(['success' => true, 'table' => $this->serialize($table)], 201);
    }

    public function update(Request $request, DineInTable $dineInTable): JsonResponse
    {
        $company = $request->user()->company;
        if ($dineInTable->company_id !== $company->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'code' => 'nullable|string|max:40',
            'seats' => 'nullable|integer|min:1|max:100',
            'isActive' => 'sometimes|boolean',
        ]);

        if (array_key_exists('name', $validated)) {
            $dineInTable->name = $validated['name'];
        }
        if (array_key_exists('code', $validated)) {
            $dineInTable->code = $validated['code'];
        }
        if (array_key_exists('seats', $validated)) {
            $dineInTable->seats = $validated['seats'];
        }
        if (array_key_exists('isActive', $validated)) {
            $dineInTable->is_active = $validated['isActive'];
        }
        $dineInTable->save();
        $dineInTable->load('company');

        return response()->json(['success' => true, 'table' => $this->serialize($dineInTable)]);
    }

    public function destroy(Request $request, DineInTable $dineInTable): JsonResponse
    {
        $company = $request->user()->company;
        if ($dineInTable->company_id !== $company->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        $dineInTable->delete();

        return response()->json(['success' => true]);
    }

    /** @return array<string, mixed> */
    protected function serialize(DineInTable $table): array
    {
        $table->loadMissing('company');

        return [
            'id' => (string) $table->id,
            'name' => $table->name,
            'code' => $table->code,
            'seats' => $table->seats,
            'isActive' => (bool) $table->is_active,
            'qrToken' => $table->qr_token,
            'orderUrl' => $table->publicOrderUrl(),
        ];
    }
}
