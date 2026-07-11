<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyIntegration;
use App\Services\Agent\Integrations\ConnectorRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index(Request $request, ConnectorRegistry $registry): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $connected = CompanyIntegration::where('company_id', $company->id)->get()->keyBy('connector_type');

        $connectors = collect($registry->catalog())->map(function (array $meta) use ($connected) {
            $row = $connected->get($meta['type']);

            return array_merge($meta, [
                'status' => $row?->status ?? 'inactive',
                'lastSyncAt' => $row?->last_sync_at?->toIso8601String(),
                'lastError' => $row?->last_error,
                'connected' => $row !== null && $row->status === 'active',
            ]);
        })->values();

        return response()->json(['connectors' => $connectors]);
    }

    public function connect(Request $request, ConnectorRegistry $registry): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company || $request->user()->role !== 'company_owner') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'connectorType' => 'required|string|max:40',
            'config' => 'nullable|array',
        ]);

        if (! $registry->has($validated['connectorType'])) {
            return response()->json(['message' => 'Unknown connector type.'], 422);
        }

        $result = $registry->connect($company, $validated['connectorType'], $validated['config'] ?? []);

        $integration = CompanyIntegration::updateOrCreate(
            ['company_id' => $company->id, 'connector_type' => $validated['connectorType']],
            [
                'status' => ($result['success'] ?? false) ? 'active' : 'error',
                'config' => $validated['config'] ?? [],
                'last_sync_at' => ($result['success'] ?? false) ? now() : null,
                'last_error' => ($result['success'] ?? false) ? null : ($result['message'] ?? 'Connection failed'),
            ],
        );

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'integration' => $integration,
            'message' => $result['message'] ?? null,
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    public function disconnect(Request $request, string $connectorType): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company || $request->user()->role !== 'company_owner') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        CompanyIntegration::where('company_id', $company->id)
            ->where('connector_type', $connectorType)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function sync(Request $request, ConnectorRegistry $registry): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company || $request->user()->role !== 'company_owner') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'connectorType' => 'required|string|max:40',
        ]);

        if (! $registry->has($validated['connectorType'])) {
            return response()->json(['message' => 'Unknown connector type.'], 422);
        }

        $integration = CompanyIntegration::where('company_id', $company->id)
            ->where('connector_type', $validated['connectorType'])
            ->first();

        if (! $integration) {
            return response()->json(['message' => 'Connector not connected.'], 422);
        }

        $result = $registry->sync($company, $validated['connectorType'], $integration->config ?? []);

        $integration->update([
            'last_sync_at' => ($result['success'] ?? false) ? now() : $integration->last_sync_at,
            'last_error' => ($result['success'] ?? false) ? null : ($result['message'] ?? 'Sync failed'),
            'status' => ($result['success'] ?? false) ? 'active' : 'error',
        ]);

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
        ], ($result['success'] ?? false) ? 200 : 422);
    }
