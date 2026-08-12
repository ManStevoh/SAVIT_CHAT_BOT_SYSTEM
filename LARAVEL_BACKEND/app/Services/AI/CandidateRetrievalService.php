<?php

namespace App\Services\AI;

use App\DTOs\ConversationState;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;

final class CandidateRetrievalService
{
    /**
     * Get top candidate products & variants for prompt context and build ephemeral token map.
     */
    public function retrieveCandidates(Company $company, ConversationState $state, string $incomingText): array
    {
        $tenantId = $company->id;
        $chatId = $state->chatId;
        $lowerText = mb_strtolower(trim($incomingText));

        $candidateProducts = [];
        $activeOptions = [];
        $tokenMap = [
            'products' => [], // 'p1' => DB ID
            'variants' => [], // 'v_red' => DB ID
            'options' => [],  // 'o1' => ['product_id' => ..., 'variant_id' => ...]
        ];

        // 1. If currently in SELECTING_VARIANT step, retrieve active pending product & its variants
        $selectingProductId = $state->pendingDraftData['selecting_product_id'] ?? null;
        if ($selectingProductId) {
            $pendingProduct = Product::where('company_id', $tenantId)
                ->where('id', $selectingProductId)
                ->where('status', 'active')
                ->first();

            if ($pendingProduct) {
                $variants = $pendingProduct->activeVariants()->get()->values();
                $vIndex = 1;
                $pToken = 'p1';
                $tokenMap['products'][$pToken] = $pendingProduct->id;

                $pVariantsFormatted = [];
                foreach ($variants as $idx => $v) {
                    $vLabel = $v->label ?? $v->name ?? 'Option';
                    $vToken = 'v_' . str_replace(' ', '_', mb_strtolower($vLabel));
                    $oToken = 'o' . ($idx + 1);

                    $tokenMap['variants'][$vToken] = $v->id;
                    $tokenMap['options'][$oToken] = [
                        'product_id' => $pendingProduct->id,
                        'variant_id' => $v->id,
                        'label' => $vLabel,
                    ];

                    $pVariantsFormatted[] = [
                        'token' => $vToken,
                        'option_token' => $oToken,
                        'label' => $vLabel,
                        'price' => (float) ($v->price ?? $pendingProduct->price),
                    ];

                    $activeOptions[] = [
                        'token' => $oToken,
                        'variant_token' => $vToken,
                        'label' => $vLabel,
                        'price' => '$' . number_format((float) ($v->price ?? $pendingProduct->price), 2),
                    ];
                }

                $candidateProducts[] = [
                    'token' => $pToken,
                    'name' => $pendingProduct->name,
                    'variants' => $pVariantsFormatted,
                ];
            }
        }

        // 2. Query candidates by text matching (name, description, category, and common synonyms)
        if (count($candidateProducts) < 3 && $lowerText !== '') {
            $keywords = [$lowerText];
            $synonymMap = [
                'footwear' => ['sneaker', 'shoe', 'boot', 'footwear', 'sandal'],
                'shoes' => ['sneaker', 'shoe', 'boot', 'footwear'],
                'shoe' => ['sneaker', 'shoe', 'boot', 'footwear'],
                'clothes' => ['trouser', 'shirt', 'dress', 'pant', 'jean', 'cloth', 'apparel', 'fashion'],
                'clothing' => ['trouser', 'shirt', 'dress', 'pant', 'jean', 'cloth', 'apparel', 'fashion'],
                'apparel' => ['trouser', 'shirt', 'dress', 'pant', 'jean', 'cloth', 'apparel', 'fashion'],
                'audio' => ['earphone', 'headphone', 'speaker', 'sound', 'audio'],
                'reading' => ['book', 'read', 'novel'],
            ];

            foreach ($synonymMap as $key => $syns) {
                if (str_contains($lowerText, $key)) {
                    $keywords = array_unique(array_merge($keywords, $syns));
                }
            }

            $matchedProducts = Product::where('company_id', $tenantId)
                ->where('status', 'active')
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->orWhere('name', 'like', '%' . $kw . '%')
                          ->orWhere('description', 'like', '%' . $kw . '%')
                          ->orWhere('category', 'like', '%' . $kw . '%')
                          ->orWhereRaw('LOWER(?) LIKE CONCAT("%", LOWER(name), "%")', [$kw]);
                    }
                })
                ->limit(5)
                ->get();

            $pCount = count($candidateProducts) + 1;
            foreach ($matchedProducts as $mp) {
                // Skip if already added as pending selection
                if ($selectingProductId && $mp->id == $selectingProductId) {
                    continue;
                }

                $pToken = 'p' . $pCount++;
                $tokenMap['products'][$pToken] = $mp->id;

                $mVariants = $mp->activeVariants()->get()->values();
                $vFormatted = [];
                foreach ($mVariants as $mv) {
                    $vLabel = $mv->label ?? $mv->name ?? 'Option';
                    $vToken = 'v_' . str_replace(' ', '_', mb_strtolower($vLabel));
                    $tokenMap['variants'][$vToken] = $mv->id;
                    $vFormatted[] = [
                        'token' => $vToken,
                        'label' => $vLabel,
                        'price' => (float) ($mv->price ?? $mp->price),
                    ];
                }

                $candidateProducts[] = [
                    'token' => $pToken,
                    'name' => $mp->name,
                    'variants' => $vFormatted,
                ];
            }
        }

        // 3. Load all active catalog products in alphabetical order (matching ResponseSpecRenderer::renderCatalogPrompt)
        $catalogProducts = Product::where('company_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        foreach ($catalogProducts as $idx => $cp) {
            $pToken = 'p' . ($idx + 1);
            if (! isset($tokenMap['products'][$pToken])) {
                $tokenMap['products'][$pToken] = $cp->id;
            }

            $cVariants = $cp->activeVariants()->get()->values();
            $vFormatted = [];
            foreach ($cVariants as $cv) {
                $vLabel = $cv->label ?? $cv->name ?? 'Option';
                $vToken = 'v_' . str_replace(' ', '_', mb_strtolower($vLabel));
                $tokenMap['variants'][$vToken] = $cv->id;
                $vFormatted[] = [
                    'token' => $vToken,
                    'label' => $vLabel,
                    'price' => (float) ($cv->price ?? $cp->price),
                ];
            }

            if (! array_filter($candidateProducts, fn ($cand) => ($cand['token'] ?? '') === $pToken)) {
                $candidateProducts[] = [
                    'token' => $pToken,
                    'name' => $cp->name,
                    'variants' => $vFormatted,
                ];
            }
        }

        // Cache token map in Redis for 15 minutes (900 seconds)
        $cacheKey = "ephemeral_tokens:{$tenantId}:{$chatId}";
        Cache::put($cacheKey, $tokenMap, 900);

        return [
            'candidate_products' => $candidateProducts,
            'active_options' => $activeOptions,
            'token_map' => $tokenMap,
        ];
    }

    /**
     * Resolve an ephemeral token to real database IDs with strict tenant & Redis cache verification.
     */
    public function resolveToken(int $tenantId, int $chatId, ?string $token, ?ConversationState $state = null, ?Company $company = null): ?array
    {
        if (! $token || trim($token) === '') {
            return null;
        }

        $token = trim($token);
        $cacheKey = "ephemeral_tokens:{$tenantId}:{$chatId}";
        $tokenMap = Cache::get($cacheKey);

        // Fallback: If cache expired (after 15 mins) and state/company provided, re-hydrate token map dynamically
        if (! is_array($tokenMap) && $state && $company) {
            $retrieved = $this->retrieveCandidates($company, $state, '');
            $tokenMap = $retrieved['token_map'] ?? null;
        }

        if (! is_array($tokenMap)) {
            return null;
        }

        $resolved = null;

        // Check options map (e.g. 'o1', 'o2')
        if (isset($tokenMap['options'][$token])) {
            $resolved = $tokenMap['options'][$token];
        }
        // Check variants map (e.g. 'v_red')
        elseif (isset($tokenMap['variants'][$token])) {
            $resolved = ['variant_id' => $tokenMap['variants'][$token]];
        }
        // Check products map (e.g. 'p1')
        elseif (isset($tokenMap['products'][$token])) {
            $resolved = ['product_id' => $tokenMap['products'][$token]];
        }
        // Fallback: If token is 'o1', 'o2', '1', '2' and no variant option matched, map to product candidate 'p1', 'p2'
        elseif (is_numeric($token) || preg_match('/^[op](\d+)$/i', $token, $m)) {
            $num = is_numeric($token) ? (int) $token : (int) $m[1];
            $pKey = 'p' . $num;
            if (isset($tokenMap['products'][$pKey])) {
                $resolved = ['product_id' => $tokenMap['products'][$pKey]];
            }
        }

        if (! $resolved) {
            return null;
        }

        // Hard Tenant Ownership Verification
        if (isset($resolved['product_id'])) {
            $validP = Product::where('company_id', $tenantId)->where('id', $resolved['product_id'])->where('status', 'active')->exists();
            if (! $validP) {
                return null;
            }
        }

        if (isset($resolved['variant_id'])) {
            $validV = ProductVariant::whereHas('product', fn ($q) => $q->where('company_id', $tenantId)->where('status', 'active'))
                ->where('id', $resolved['variant_id'])
                ->exists();
            if (! $validV) {
                return null;
            }
        }

        return $resolved;
    }
}
