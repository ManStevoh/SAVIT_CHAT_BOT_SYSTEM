<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\AI\AiModelResolver;
use App\Services\AI\AiOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $providers = AiProvider::query()
            ->with(['models' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (AiProvider $p) => [
                'id' => (string) $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'apiBaseUrl' => $p->api_base_url,
                'apiKeyConfigured' => $p->hasConfiguredApiKey(),
                'isEnabled' => $p->is_enabled,
                'sortOrder' => $p->sort_order,
                'models' => $p->models->map(fn (AiModel $m) => $this->formatModel($m))->values(),
            ]);

        return response()->json(['providers' => $providers]);
    }

    public function orchestration(AiOrchestrator $orchestrator): JsonResponse
    {
        return response()->json($orchestrator->routingMap());
    }

    public function updateProvider(Request $request, AiProvider $provider): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'apiBaseUrl' => 'nullable|string|max:500',
            'apiKey' => 'nullable|string|max:500',
            'isEnabled' => 'sometimes|boolean',
            'sortOrder' => 'sometimes|integer|min:0|max:999',
        ]);

        $updates = [];
        if (array_key_exists('name', $validated)) {
            $updates['name'] = $validated['name'];
        }
        if (array_key_exists('apiBaseUrl', $validated)) {
            $updates['api_base_url'] = $validated['apiBaseUrl'];
        }
        if (array_key_exists('isEnabled', $validated)) {
            $updates['is_enabled'] = $validated['isEnabled'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $updates['sort_order'] = $validated['sortOrder'];
        }
        if (! empty($validated['apiKey']) && $validated['apiKey'] !== '********') {
            $updates['api_key'] = $validated['apiKey'];
        }

        $provider->update($updates);
        AiModelResolver::clearCache();

        return response()->json(['success' => true, 'provider' => [
            'id' => (string) $provider->id,
            'slug' => $provider->slug,
            'apiKeyConfigured' => $provider->fresh()->hasConfiguredApiKey(),
        ]]);
    }

    public function storeModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'providerId' => 'required|integer|exists:ai_providers,id',
            'modelKey' => 'required|string|max:120',
            'displayName' => 'required|string|max:120',
            'capability' => ['required', Rule::in(AiModel::capabilities())],
            'inputCostPerMillion' => 'nullable|numeric|min:0',
            'outputCostPerMillion' => 'nullable|numeric|min:0',
            'maxOutputTokens' => 'nullable|integer|min:1|max:200000',
            'isEnabled' => 'sometimes|boolean',
            'isPlatformDefault' => 'sometimes|boolean',
        ]);

        if (! empty($validated['isPlatformDefault'])) {
            AiModel::where('capability', $validated['capability'])->update(['is_platform_default' => false]);
        }

        $model = AiModel::create([
            'ai_provider_id' => $validated['providerId'],
            'model_key' => $validated['modelKey'],
            'display_name' => $validated['displayName'],
            'capability' => $validated['capability'],
            'input_cost_per_million' => $validated['inputCostPerMillion'] ?? 0,
            'output_cost_per_million' => $validated['outputCostPerMillion'] ?? 0,
            'max_output_tokens' => $validated['maxOutputTokens'] ?? 512,
            'is_enabled' => $validated['isEnabled'] ?? true,
            'is_platform_default' => $validated['isPlatformDefault'] ?? false,
        ]);

        AiModelResolver::clearCache();

        return response()->json(['success' => true, 'model' => $this->formatModel($model)]);
    }

    public function updateModel(Request $request, AiModel $model): JsonResponse
    {
        $validated = $request->validate([
            'displayName' => 'sometimes|string|max:120',
            'inputCostPerMillion' => 'sometimes|numeric|min:0',
            'outputCostPerMillion' => 'sometimes|numeric|min:0',
            'maxOutputTokens' => 'sometimes|integer|min:1|max:200000',
            'isEnabled' => 'sometimes|boolean',
            'isPlatformDefault' => 'sometimes|boolean',
            'sortOrder' => 'sometimes|integer|min:0|max:999',
        ]);

        if (! empty($validated['isPlatformDefault'])) {
            AiModel::where('capability', $model->capability)->where('id', '!=', $model->id)->update(['is_platform_default' => false]);
        }

        $model->update([
            'display_name' => $validated['displayName'] ?? $model->display_name,
            'input_cost_per_million' => $validated['inputCostPerMillion'] ?? $model->input_cost_per_million,
            'output_cost_per_million' => $validated['outputCostPerMillion'] ?? $model->output_cost_per_million,
            'max_output_tokens' => $validated['maxOutputTokens'] ?? $model->max_output_tokens,
            'is_enabled' => $validated['isEnabled'] ?? $model->is_enabled,
            'is_platform_default' => $validated['isPlatformDefault'] ?? $model->is_platform_default,
            'sort_order' => $validated['sortOrder'] ?? $model->sort_order,
        ]);

        AiModelResolver::clearCache();

        return response()->json(['success' => true, 'model' => $this->formatModel($model->fresh())]);
    }

    public function destroyModel(AiModel $model): JsonResponse
    {
        $model->delete();
        AiModelResolver::clearCache();

        return response()->json(['success' => true]);
    }

    private function formatModel(AiModel $m): array
    {
        return [
            'id' => (string) $m->id,
            'providerId' => (string) $m->ai_provider_id,
            'modelKey' => $m->model_key,
            'displayName' => $m->display_name,
            'capability' => $m->capability,
            'inputCostPerMillion' => (float) $m->input_cost_per_million,
            'outputCostPerMillion' => (float) $m->output_cost_per_million,
            'maxOutputTokens' => (int) $m->max_output_tokens,
            'isEnabled' => $m->is_enabled,
            'isPlatformDefault' => $m->is_platform_default,
            'sortOrder' => $m->sort_order,
        ];
    }
}
