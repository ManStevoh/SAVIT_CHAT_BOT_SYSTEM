<?php

namespace App\Services\Agent\Company;

use App\Models\AgentReasoningTrace;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
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
You are the reasoning engine for an AI commerce company. Analyze the latest customer message in the context of the recent dialogue.
Infer intent from meaning in any language or style — never require exact keywords or sample phrases.
Treat short replies (affirmations, slang, emojis, "ok", "sawa", "ndio", "go on", etc.) relative to the bot's last question or offer.
Return JSON only:
{
  "understanding": "what the customer needs in plain language, including how this reply continues the prior bot turn",
  "dialogue_continuity": "how this message relates to the bot's last ask/offer",
  "customer_stance": "affirm|deny|new_request|clarify|want_human|reject_human|other",
  "action_required": true,
  "action_kind": "inform|lookup|create_order|pay|send_document|track|refund|handoff|remember|other",
  "hypotheses": ["possible interpretation 1", "possible interpretation 2"],
  "options": [{"label":"A","approach":"...","pros":"...","cons":"..."}],
  "chosen_plan": "which approach and why; if action_required, which tool capability to execute this turn",
  "missing_info": ["what to clarify if needed"],
  "specialist_council": {
    "sales": "sales agent perspective",
    "support": "support agent perspective",
    "logistics": "logistics/ops perspective"
  },
  "time_context": "urgency, deadlines, event timing if any",
  "geo_context": "location/delivery implications if any"
}
Rules:
- If the bot offered to confirm/create an order / share payment / send an invoice and the customer affirms in any wording, set action_required=true and action_kind to create_order, pay, or send_document as appropriate — never handoff.
- customer_stance=want_human only when they clearly want a person. reject_human when they refuse a transfer.
- Prefer tool execution over promising. Never expose this JSON to the customer.
TEXT;

        $recentDialogue = $this->recentDialogueForPrompt($chat, $incomingMessage);

        $context = implode("\n", array_filter([
            $this->digitalTwin->getForPrompt($company),
            $this->businessGoals->getForPrompt($company),
            $this->operatingGuides->getForPrompt($company),
            $this->intentChains->getForPrompt($company, $customerPhone),
            $this->sentiment->guidanceForPrompt($sentiment),
            'Customer name: '.($customerName ?? 'unknown'),
            $recentDialogue,
        ]));

        try {
            $result = $this->aiGateway->chatCompletion(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => "Context:\n{$context}\n\nLatest customer message:\n{$incomingMessage}"],
                ],
                useCase: 'agent_reasoning',
                company: $company,
                chatId: (int) $chat->id,
                maxTokens: 700,
                temperature: 0.2,
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
        if (! empty($trace['dialogue_continuity'])) {
            $parts[] = 'Dialogue continuity: '.$trace['dialogue_continuity'];
        }
        if (! empty($trace['customer_stance'])) {
            $parts[] = 'Customer stance: '.$trace['customer_stance'];
        }
        $actionRequired = array_key_exists('action_required', $trace)
            ? (filter_var($trace['action_required'], FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no')
            : null;
        if ($actionRequired !== null) {
            $parts[] = 'Action required: '.$actionRequired;
        }
        if (! empty($trace['action_kind'])) {
            $parts[] = 'Action kind: '.$trace['action_kind'];
        }
        if ($actionRequired === 'yes') {
            $parts[] = 'Directive: continue the open thread smoothly; execute matching tool(s) this turn — do not only promise, and do not transfer_to_human unless stance is want_human.';
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

    private function recentDialogueForPrompt(Chat $chat, string $incomingMessage): string
    {
        $rows = Message::query()
            ->where('chat_id', $chat->id)
            ->orderByDesc('id')
            ->limit(8)
            ->get(['sender', 'content'])
            ->reverse();

        if ($rows->isEmpty()) {
            return "Recent dialogue:\n(none yet)\nLatest customer message: {$incomingMessage}";
        }

        $lines = ['Recent dialogue (oldest → newest):'];
        foreach ($rows as $msg) {
            $role = $msg->sender === 'customer' ? 'Customer' : 'Business';
            $content = trim((string) $msg->content);
            if ($content === '') {
                continue;
            }
            $lines[] = "- {$role}: ".mb_substr($content, 0, 400);
        }
        $lastBusiness = $rows->reverse()->first(fn ($m) => $m->sender !== 'customer' && trim((string) $m->content) !== '');
        if ($lastBusiness) {
            $lines[] = 'Bot last said (honor this open offer/question when interpreting the reply): '
                .mb_substr(trim((string) $lastBusiness->content), 0, 500);
        }

        return implode("\n", $lines);
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
