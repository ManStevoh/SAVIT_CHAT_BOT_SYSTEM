<?php

namespace App\Services\Agent\Company;

use App\Models\AgentReasoningTrace;
use App\Models\Chat;
use App\Models\Company;
use App\Services\Agent\BusinessGoalService;
use App\Services\AI\AiGateway;
use Illuminate\Support\Facades\Log;

/**
 * Observe → Understand → Hypothesize → Evaluate → Plan (structured reasoning trace).
 */
final class ReasoningEngineService
{
    public function __construct(
        protected AiGateway $aiGateway,
        protected MessageSentimentService $sentiment,
        protected CompanyDigitalTwinService $digitalTwin,
        protected BusinessGoalService $businessGoals,
        protected AgentOperatingGuideService $operatingGuides,
        protected CustomerIntentChainService $intentChains,
    ) {}

    /**
     * @return array{prompt_block: string, trace: array<string, mixed>|null, sentiment: array<string, mixed>}
     */
    public function reason(
        Company $company,
        Chat $chat,
        string $customerPhone,
        ?string $customerName,
        string $incomingMessage,
    ): array {
        $company->loadMissing('settings');
        $sentiment = $this->sentiment->detect($incomingMessage);
        $chat->update(['detected_sentiment' => $sentiment['label']]);

        if (! config('agent.company.reasoning_enabled', true)) {
            return [
                'prompt_block' => $this->sentiment->guidanceForPrompt($sentiment),
                'trace' => null,
                'sentiment' => $sentiment,
            ];
        }

        $started = microtime(true);
        $system = <<<'TEXT'
You are the reasoning engine for an AI commerce company. Analyze the customer message internally.
Return JSON only:
{
  "understanding": "what the customer needs",
  "hypotheses": ["possible interpretation 1", "possible interpretation 2"],
  "options": [{"label":"A","approach":"...","pros":"...","cons":"..."}],
  "chosen_plan": "which approach and why (consider business goals)",
  "missing_info": ["what to clarify if needed"],
  "specialist_council": {
    "sales": "sales agent perspective",
    "support": "support agent perspective",
    "logistics": "logistics/ops perspective"
  },
  "time_context": "urgency, deadlines, event timing if any",
  "geo_context": "location/delivery implications if any"
}
Never expose this JSON to the customer.
TEXT;

        $context = implode("\n", array_filter([
            $this->digitalTwin->getForPrompt($company),
            $this->businessGoals->getForPrompt($company),
            $this->operatingGuides->getForPrompt($company),
            $this->intentChains->getForPrompt($company, $customerPhone),
            $this->sentiment->guidanceForPrompt($sentiment),
            'Customer name: '.($customerName ?? 'unknown'),
        ]));

        try {
            $result = $this->aiGateway->chatCompletion(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => "Context:\n{$context}\n\nCustomer message:\n{$incomingMessage}"],
                ],
                useCase: 'agent_reasoning',
                company: $company,
                chatId: (int) $chat->id,
                maxTokens: 600,
                temperature: 0.3,
                jsonMode: true,
                timeoutSeconds: 25,
            );

            if (! $result->success || ! $result->content) {
                return $this->fallback($sentiment);
            }

            $trace = json_decode($result->content, true);
            if (! is_array($trace)) {
                return $this->fallback($sentiment);
            }

            $latencyMs = (int) round((microtime(true) - $started) * 1000);
            AgentReasoningTrace::create([
                'company_id' => $company->id,
                'chat_id' => $chat->id,
                'incoming_message' => mb_substr($incomingMessage, 0, 2000),
                'trace' => $trace,
                'chosen_plan' => mb_substr((string) ($trace['chosen_plan'] ?? ''), 0, 500),
                'latency_ms' => $latencyMs,
                'created_at' => now(),
            ]);

            $this->intentChains->advanceFromReasoning($company, $customerPhone, $trace);

            return [
                'prompt_block' => $this->formatForChiefAgent($trace, $sentiment),
                'trace' => $trace,
                'sentiment' => $sentiment,
            ];
        } catch (\Throwable $e) {
            Log::warning('Reasoning engine failed', ['error' => $e->getMessage(), 'company_id' => $company->id]);

            return $this->fallback($sentiment);
        }
    }

    /**
     * @param  array<string, mixed>  $trace
     * @param  array<string, mixed>  $sentiment
     */
    private function formatForChiefAgent(array $trace, array $sentiment): string
    {
        $parts = ['Internal reasoning (never reveal to customer):'];
        if (! empty($trace['understanding'])) {
            $parts[] = 'Understanding: '.$trace['understanding'];
        }
        if (! empty($trace['chosen_plan'])) {
            $parts[] = 'Plan: '.$trace['chosen_plan'];
        }
        if (! empty($trace['specialist_council']) && is_array($trace['specialist_council'])) {
            $parts[] = 'Specialist council:';
            foreach ($trace['specialist_council'] as $role => $note) {
                if (is_string($note) && trim($note) !== '') {
                    $parts[] = "- {$role}: {$note}";
                }
            }
        }
        if (! empty($trace['time_context'])) {
            $parts[] = 'Time: '.$trace['time_context'];
        }
        if (! empty($trace['geo_context'])) {
            $parts[] = 'Geography: '.$trace['geo_context'];
        }
        $sentimentHint = $this->sentiment->guidanceForPrompt($sentiment);
        if ($sentimentHint !== '') {
            $parts[] = $sentimentHint;
        }

        return implode("\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $sentiment
     * @return array{prompt_block: string, trace: null, sentiment: array<string, mixed>}
     */
    private function fallback(array $sentiment): array
    {
        return [
            'prompt_block' => $this->sentiment->guidanceForPrompt($sentiment),
            'trace' => null,
            'sentiment' => $sentiment,
        ];
    }
}
