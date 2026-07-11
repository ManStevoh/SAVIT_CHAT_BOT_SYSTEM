<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Company;

/**
 * AI governance (#53) — policy, approval, audit metadata.
 */
final class GovernanceService
{
    /**
     * @return array<string, mixed>
     */
    public function buildContext(Company $company, string $confidenceAction, float $confidence): array
    {
        return [
            'confidence_action' => $confidenceAction,
            'confidence' => $confidence,
            'human_approval_required' => $confidenceAction === 'escalate',
            'policies' => [
                'high_risk_tools_require_approval' => true,
                'no_unapproved_discounts' => true,
                'explain_before_recommend' => true,
                'rollback_supported' => false,
            ],
            'audit_fields' => ['goal', 'reasoning', 'tools', 'data', 'confidence', 'outcome'],
        ];
    }

    /**
     * @param  array<string, mixed>  $governance
     * @param  array<string, mixed>  $cognitiveContext
     * @return array<string, mixed>
     */
    public function enrichTrustPayload(array $governance, array $cognitiveContext): array
    {
        return [
            'governance' => $governance,
            'perception' => $cognitiveContext['perception'] ?? null,
            'debate_summary' => array_keys($cognitiveContext['debate'] ?? []),
            'confidence_action' => $cognitiveContext['confidence_action'] ?? null,
            'episode_id' => $cognitiveContext['episode_id'] ?? null,
        ];
    }
}
