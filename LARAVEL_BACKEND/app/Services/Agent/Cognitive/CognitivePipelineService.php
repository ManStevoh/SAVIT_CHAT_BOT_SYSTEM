<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Chat;
use App\Models\CognitiveEpisode;
use App\Models\Company;
use App\Services\Agent\Company\CompanyDigitalTwinService;
use App\Services\Agent\Company\ReasoningEngineService;

/**
 * Cognitive architecture orchestrator (#36) — perception → reasoning → debate → confidence.
 */
final class CognitivePipelineService
{
    public function __construct(
        protected PerceptionService $perception,
        protected ReasoningEngineService $reasoning,
        protected InternalDebateService $debate,
        protected ConfidenceScoringService $confidence,
        protected BusinessDnaService $businessDna,
        protected EconomicReasoningService $economic,
        protected GovernanceService $governance,
        protected MetaLearningService $metaLearning,
        protected CompanyDigitalTwinService $digitalTwin,
    ) {}

    /**
     * @return array{
     *   perception: array<string, mixed>,
     *   reasoning: array<string, mixed>,
     *   debate: array<string, string>,
     *   confidence: float,
     *   confidence_action: string,
     *   prompt_block: string,
     *   episode_id: int,
     *   governance: array<string, mixed>
     * }
     */
    public function processTurn(
        Company $company,
        Chat $chat,
        string $customerPhone,
        ?string $customerName,
        string $incomingMessage,
    ): array {
        $perception = $this->perception->perceive($company, $incomingMessage, $customerPhone);
        $reasoning = $this->reasoning->reason($company, $chat, $customerPhone, $customerName, $incomingMessage);

        $company->loadMissing('settings');
        $councilEnabled = (bool) ($company->settings?->agent_council_enabled ?? false);

        if ($councilEnabled) {
            $debate = $this->debate->debate($company, $chat, $incomingMessage, $perception, $reasoning['trace'] ?? null);
        } else {
            $debate = [
                'chief' => 'Council disabled — respond directly using tools and business DNA.',
            ];
        }

        $confidence = $this->confidence->score($perception, $reasoning);
        $action = $this->confidence->actionForScore($confidence);

        $promptBlock = implode("\n\n", array_filter([
            $this->digitalTwin->getForPrompt($company),
            $this->businessDna->getForPrompt($company),
            $this->perception->guidanceForPrompt($perception),
            $councilEnabled ? $this->debate->guidanceForPrompt($debate) : null,
            $this->economic->guidanceForPrompt($company, $customerPhone, $perception),
            $this->metaLearning->guidanceForCompany($company),
            $this->confidence->guidanceForPrompt($confidence, $action),
            $reasoning['prompt_block'] ?? '',
        ]));

        $governance = $this->governance->buildContext($company, $action, $confidence);

        $episode = CognitiveEpisode::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'perception' => $perception,
            'debate' => $debate,
            'confidence' => $confidence,
            'confidence_action' => $action,
            'governance' => $governance,
            'created_at' => now(),
        ]);

        return [
            'perception' => $perception,
            'reasoning' => $reasoning,
            'debate' => $debate,
            'confidence' => $confidence,
            'confidence_action' => $action,
            'prompt_block' => $promptBlock,
            'episode_id' => (int) $episode->id,
            'governance' => $governance,
        ];
    }

    public function finalizeEpisode(int $episodeId, array $critique, string $outcome): void
    {
        CognitiveEpisode::where('id', $episodeId)->update([
            'critique' => $critique,
            'outcome' => $outcome,
        ]);
    }
}
