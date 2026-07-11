<?php

namespace App\Services\Agent\Platform;

use App\Models\CommerceExperiment;
use App\Models\CommerceExperimentVariant;
use App\Models\Company;
use Illuminate\Support\Collection;

/**
 * A/B promotion experiments with evidence-based winner selection.
 */
final class CommerceExperimentService
{
    /**
     * @param  array{message: string}  $variantA
     * @param  array{message: string}  $variantB
     */
    public function createPromotionExperiment(
        Company $company,
        string $name,
        array $variantA,
        array $variantB,
    ): CommerceExperiment {
        $experiment = CommerceExperiment::create([
            'company_id' => $company->id,
            'name' => $name,
            'experiment_type' => 'promotion_ab',
            'status' => 'running',
            'metric_key' => 'conversion_rate',
            'started_at' => now(),
            'config' => ['channel' => 'whatsapp_proactive'],
        ]);

        CommerceExperimentVariant::create([
            'experiment_id' => $experiment->id,
            'variant_key' => 'a',
            'label' => 'Variant A',
            'payload' => $variantA,
        ]);
        CommerceExperimentVariant::create([
            'experiment_id' => $experiment->id,
            'variant_key' => 'b',
            'label' => 'Variant B',
            'payload' => $variantB,
        ]);

        return $experiment->load('variants');
    }

    public function activePromotionExperiment(int $companyId): ?CommerceExperiment
    {
        return CommerceExperiment::where('company_id', $companyId)
            ->where('experiment_type', 'promotion_ab')
            ->where('status', 'running')
            ->with('variants')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Pick variant for next assignment (round-robin by lower assignments).
     */
    public function assignVariant(CommerceExperiment $experiment): ?CommerceExperimentVariant
    {
        $variants = $experiment->variants()->orderBy('assignments_count')->get();
        if ($variants->isEmpty()) {
            return null;
        }

        /** @var CommerceExperimentVariant $pick */
        $pick = $variants->first();
        $pick->increment('assignments_count');

        return $pick->fresh();
    }

    public function messageForVariant(?CommerceExperimentVariant $variant, string $fallback): string
    {
        if ($variant === null) {
            return $fallback;
        }

        $payload = $variant->payload ?? [];

        return trim((string) ($payload['message'] ?? $fallback));
    }

    public function recordConversion(int $experimentId, int $variantId, float $revenue = 0): void
    {
        $variant = CommerceExperimentVariant::where('experiment_id', $experimentId)
            ->find($variantId);
        if (! $variant) {
            return;
        }

        $variant->increment('conversions_count');
        if ($revenue > 0) {
            $variant->update(['revenue_total' => (float) $variant->revenue_total + $revenue]);
        }
    }

    /**
     * @return array{winner_id: ?int, variants: Collection<int, CommerceExperimentVariant>}
     */
    public function evaluateWinner(CommerceExperiment $experiment, int $minAssignments = 20): array
    {
        $variants = $experiment->variants()->get();
        $eligible = $variants->filter(fn ($v) => $v->assignments_count >= max(5, (int) ($minAssignments / 2)));

        if ($eligible->count() < 2) {
            return ['winner_id' => null, 'variants' => $variants];
        }

        $winner = $eligible->sortByDesc(fn ($v) => [$v->conversionRate(), $v->revenue_total])->first();

        if ($winner) {
            $experiment->update([
                'status' => 'completed',
                'winner_variant_id' => $winner->id,
                'ended_at' => now(),
            ]);
        }

        return ['winner_id' => $winner?->id, 'variants' => $variants->fresh()];
    }
}
