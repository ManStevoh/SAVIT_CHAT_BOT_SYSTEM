<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Platform\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlements,
    ) {}

    public function index(): JsonResponse
    {
        $plans = Plan::orderBy('sort_order')->orderBy('id')->get();
        $data = $plans->map(fn (Plan $p) => $this->planToArray($p));

        return response()->json($data->values()->all());
    }

    private function planToArray(Plan $p): array
    {
        return [
            'id' => (string) $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'priceDisplay' => $p->price_display,
            'priceAmount' => $p->price_amount !== null ? (float) $p->price_amount : null,
            'description' => $p->description ?? '',
            'features' => $p->features ?? [],
            'popular' => (bool) $p->popular,
            'cta' => $p->cta ?? 'Start Free Trial',
            'sortOrder' => $p->sort_order,
            'stripePriceId' => $p->stripe_price_id,
            'isFree' => (bool) ($p->is_free ?? false),
            'hasTrial' => (bool) ($p->has_trial ?? false),
            'trialDays' => $p->trial_days !== null ? (int) $p->trial_days : null,
            'trialElapsedAction' => $p->trial_elapsed_action,
            'entitlements' => $this->entitlements->entitlementsForApi($p),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entitlementsRules(): array
    {
        return [
            'entitlements' => 'sometimes|array',
            'entitlements.messages' => 'nullable|integer|min:0',
            'entitlements.messagesUnlimited' => 'sometimes|boolean',
            'entitlements.team' => 'sometimes|integer|min:1|max:1000',
            'entitlements.whatsappNumbers' => 'sometimes|integer|min:1|max:100',
            'entitlements.aiCostUsd' => 'nullable|numeric|min:0',
            'entitlements.aiModelModes' => 'sometimes|array',
            'entitlements.aiModelModes.*' => 'string|in:auto,platform_default,specific',
            'entitlements.allowByok' => 'sometimes|boolean',
            'entitlements.credentialModes' => 'sometimes|array',
            'entitlements.credentialModes.*' => 'string|in:platform,company_preferred,company',
            'entitlements.apiAccess' => 'sometimes|boolean',
            'entitlements.analytics' => 'sometimes|boolean',
            'entitlements.attribution' => 'sometimes|boolean',
            'entitlements.aiPostsPerMonth' => 'sometimes|integer|min:0|max:100000',
            'entitlements.aiImagesPerMonth' => 'sometimes|integer|min:0|max:100000',
            'entitlements.socialPlatforms' => 'sometimes|integer|min:0|max:100',
            'entitlements.growthEnabled' => 'sometimes|boolean',
            'entitlements.agentCommerce' => 'sometimes|boolean',
            'entitlements.allowPhysical' => 'sometimes|boolean',
            'entitlements.allowDigital' => 'sometimes|boolean',
            'entitlements.allowService' => 'sometimes|boolean',
            'entitlements.allowBookings' => 'sometimes|boolean',
            'entitlements.maxBookingsPerMonth' => 'nullable|integer|min:0|max:1000000',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|null
     */
    private function entitlementsFromRequest(array $validated, ?string $slug): ?array
    {
        if (! array_key_exists('entitlements', $validated) || ! is_array($validated['entitlements'])) {
            return null;
        }

        $e = $validated['entitlements'];
        $snake = [
            'messages' => ! empty($e['messagesUnlimited']) ? null : ($e['messages'] ?? null),
            'team' => $e['team'] ?? null,
            'whatsapp_numbers' => $e['whatsappNumbers'] ?? null,
            'ai_cost_usd' => $e['aiCostUsd'] ?? null,
            'ai_model_modes' => $e['aiModelModes'] ?? null,
            'allow_byok' => $e['allowByok'] ?? null,
            'credential_modes' => $e['credentialModes'] ?? null,
            'api_access' => $e['apiAccess'] ?? null,
            'analytics' => $e['analytics'] ?? null,
            'attribution' => $e['attribution'] ?? null,
            'ai_posts_per_month' => $e['aiPostsPerMonth'] ?? null,
            'ai_images_per_month' => $e['aiImagesPerMonth'] ?? null,
            'social_platforms' => $e['socialPlatforms'] ?? null,
            'growth_enabled' => $e['growthEnabled'] ?? null,
            'agent_commerce' => $e['agentCommerce'] ?? null,
            'allow_physical' => $e['allowPhysical'] ?? null,
            'allow_digital' => $e['allowDigital'] ?? null,
            'allow_service' => $e['allowService'] ?? null,
            'allow_bookings' => $e['allowBookings'] ?? null,
            'max_bookings_per_month' => $e['maxBookingsPerMonth'] ?? null,
        ];

        // Drop nulls that mean "not provided" except messages (null = unlimited when messagesUnlimited).
        $input = [];
        foreach ($snake as $key => $value) {
            if ($key === 'messages') {
                if (! empty($e['messagesUnlimited'])) {
                    $input['messages'] = null;
                } elseif (array_key_exists('messages', $e)) {
                    $input['messages'] = $e['messages'];
                }

                continue;
            }
            if ($value !== null) {
                $input[$key] = $value;
            }
        }
        if (array_key_exists('maxBookingsPerMonth', $e)) {
            $input['max_bookings_per_month'] = $e['maxBookingsPerMonth'];
        }

        return EntitlementService::normalizeAdminEntitlements($input, $slug);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans,slug|regex:/^[a-z0-9_-]+$/',
            'priceDisplay' => 'required|string|max:50',
            'priceAmount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'features' => 'nullable|array',
            'features.*' => 'string|max:255',
            'popular' => 'boolean',
            'cta' => 'nullable|string|max:100',
            'sortOrder' => 'nullable|integer|min:0',
            'stripePriceId' => 'nullable|string|max:255',
            'isFree' => 'boolean',
            'hasTrial' => 'boolean',
            'trialDays' => 'nullable|integer|min:1|max:365',
            'trialElapsedAction' => 'nullable|string|max:100',
        ], $this->entitlementsRules()));

        $entitlements = $this->entitlementsFromRequest($validated, $validated['slug'])
            ?? EntitlementService::normalizeAdminEntitlements([], $validated['slug']);

        $plan = Plan::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'price_display' => $validated['priceDisplay'],
            'price_amount' => isset($validated['priceAmount']) ? (float) $validated['priceAmount'] : null,
            'description' => $validated['description'] ?? null,
            'features' => $validated['features'] ?? [],
            'popular' => $validated['popular'] ?? false,
            'cta' => $validated['cta'] ?? 'Start Free Trial',
            'sort_order' => $validated['sortOrder'] ?? 0,
            'stripe_price_id' => $validated['stripePriceId'] ?? null,
            'is_free' => $validated['isFree'] ?? false,
            'has_trial' => $validated['hasTrial'] ?? false,
            'trial_days' => $validated['trialDays'] ?? null,
            'trial_elapsed_action' => $validated['trialElapsedAction'] ?? null,
            'entitlements' => $entitlements,
        ]);

        return response()->json([
            'success' => true,
            'plan' => $this->planToArray($plan),
        ], 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'name' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9_-]+$/', Rule::unique('plans')->ignore($plan->id)],
            'priceDisplay' => 'sometimes|string|max:50',
            'priceAmount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'features' => 'nullable|array',
            'features.*' => 'string|max:255',
            'popular' => 'boolean',
            'cta' => 'nullable|string|max:100',
            'sortOrder' => 'nullable|integer|min:0',
            'stripePriceId' => 'nullable|string|max:255',
            'isFree' => 'boolean',
            'hasTrial' => 'boolean',
            'trialDays' => 'nullable|integer|min:1|max:365',
            'trialElapsedAction' => 'nullable|string|max:100',
        ], $this->entitlementsRules()));

        $data = [];
        if (array_key_exists('name', $validated)) {
            $data['name'] = $validated['name'];
        }
        if (array_key_exists('slug', $validated)) {
            $data['slug'] = $validated['slug'];
        }
        if (array_key_exists('priceDisplay', $validated)) {
            $data['price_display'] = $validated['priceDisplay'];
        }
        if (array_key_exists('priceAmount', $validated)) {
            $data['price_amount'] = $validated['priceAmount'] !== null && $validated['priceAmount'] !== ''
                ? (float) $validated['priceAmount']
                : null;
        }
        if (array_key_exists('description', $validated)) {
            $data['description'] = $validated['description'];
        }
        if (array_key_exists('features', $validated)) {
            $data['features'] = $validated['features'];
        }
        if (array_key_exists('popular', $validated)) {
            $data['popular'] = (bool) $validated['popular'];
        }
        if (array_key_exists('cta', $validated)) {
            $data['cta'] = $validated['cta'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $data['sort_order'] = (int) $validated['sortOrder'];
        }
        if (array_key_exists('stripePriceId', $validated)) {
            $data['stripe_price_id'] = $validated['stripePriceId'] ?: null;
        }
        if (array_key_exists('isFree', $validated)) {
            $data['is_free'] = (bool) $validated['isFree'];
        }
        if (array_key_exists('hasTrial', $validated)) {
            $data['has_trial'] = (bool) $validated['hasTrial'];
        }
        if (array_key_exists('trialDays', $validated)) {
            $data['trial_days'] = $validated['trialDays'] ?? null;
        }
        if (array_key_exists('trialElapsedAction', $validated)) {
            $data['trial_elapsed_action'] = $validated['trialElapsedAction'] ?: null;
        }

        $slug = $data['slug'] ?? $plan->slug;
        $entitlements = $this->entitlementsFromRequest($validated, $slug);
        if ($entitlements !== null) {
            $data['entitlements'] = $entitlements;
        }

        $plan->update($data);

        return response()->json([
            'success' => true,
            'plan' => $this->planToArray($plan->fresh()),
        ]);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $plan->delete();

        return response()->json(['success' => true]);
    }
}
