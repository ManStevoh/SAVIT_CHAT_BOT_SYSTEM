<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\AI\CompanyAiCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiModelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $credentials = app(CompanyAiCredentialService::class);

        $models = AiModel::query()
            ->where('capability', AiModel::CAPABILITY_CHAT)
            ->where('is_enabled', true)
            ->whereHas('provider', fn ($q) => $q->where('is_enabled', true))
            ->with('provider')
            ->orderBy('sort_order')
            ->get()
            ->filter(function (AiModel $model) use ($company, $credentials) {
                $provider = $model->provider;

                return $credentials->resolve($company, $provider)['key'] !== null;
            })
            ->map(fn (AiModel $m) => [
                'id' => (string) $m->id,
                'displayName' => $m->display_name,
                'modelKey' => $m->model_key,
                'provider' => $m->provider->name,
                'providerSlug' => $m->provider->slug,
                'inputCostPerMillion' => (float) $m->input_cost_per_million,
                'outputCostPerMillion' => (float) $m->output_cost_per_million,
                'isPlatformDefault' => $m->is_platform_default,
            ])
            ->values();

        return response()->json(['models' => $models]);
    }
}
