<?php

namespace App\Services\Agent;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Services\Agent\Brain\UnifiedCompanyBrainService;
use App\Services\Agent\Cognitive\CognitivePipelineService;
use App\Services\Agent\Cognitive\GovernanceService;
use App\Services\Agent\Cognitive\SelfCritiqueService;
use App\Services\Agent\Cognitive\StrategicMemoryService;
use App\Services\Agent\Company\AgentOperatingGuideService;
use App\Services\Agent\Company\CompanyDigitalTwinService;
use App\Services\Agent\Company\CustomerIntentChainService;
use App\Services\Agent\Platform\AgentTrustService;
use App\Services\Agent\Platform\BusinessWorldModelService;
use App\Services\Agent\Platform\OrganizationalMemoryService;
use App\Services\Agent\Platform\SkillModuleRegistry;
use App\Services\AI\ReplyGuardService;
use App\Services\AI\SystemPromptBuilder;
use App\Services\Conversation\ConversationLearningRecorder;
use App\Services\ConversationLearningService;

/**
 * Primary conversational OS for commerce — tool-using agent that owns the customer journey.
 */
final class CommerceAgentOrchestrator
{
    public function __construct(
        protected AgentToolRegistry $tools,
        protected AgentToolRunner $toolRunner,
        protected AgentChatService $agentChat,
        protected CustomerMemoryService $customerMemory,
        protected AgentMemoryService $agentMemory,
        protected BusinessGoalService $businessGoals,
        protected SystemPromptBuilder $systemPromptBuilder,
        protected ReplyGuardService $replyGuard,
        protected ConversationLearningRecorder $learningRecorder,
        protected CognitivePipelineService $cognitive,
        protected CompanyDigitalTwinService $digitalTwin,
        protected AgentOperatingGuideService $operatingGuides,
        protected CustomerIntentChainService $intentChains,
        protected BusinessWorldModelService $worldModel,
        protected OrganizationalMemoryService $orgMemory,
        protected SkillModuleRegistry $skills,
        protected AgentTrustService $trust,
        protected SelfCritiqueService $critique,
        protected GovernanceService $governance,
        protected StrategicMemoryService $strategicMemory,
        protected UnifiedCompanyBrainService $companyBrain,
        protected AgentCustomerIntelligenceContext $customerIntelligence,
        protected ConversationLearningService $learningService,
    ) {}

    /**
     * @return array{reply: ?string, route: string, handoff: bool, order_flow_reply: ?string}
     */
    public function run(
        Company $company,
        Chat $chat,
        string $customerPhone,
        ?string $customerName,
        string $incomingMessage,
    ): array {
        $company->loadMissing('settings');

        $cognitiveContext = $this->cognitive->processTurn(
            $company, $chat, $customerPhone, $customerName, $incomingMessage,
        );
        $reasoning = $cognitiveContext['reasoning'];

        $context = new AgentToolContext($company, $chat, $customerPhone, $customerName, $incomingMessage);
        $messages = $this->buildMessages($company, $chat, $context, $incomingMessage, $cognitiveContext['prompt_block'] ?? '');

        $maxIterations = (int) config('agent.max_loop_iterations', 12);
        $maxToolCalls = (int) config('agent.max_tool_calls_per_turn', 16);
        $toolCallCount = 0;
        $toolsUsed = [];
        $handoff = false;
        $orderFlowReply = null;
        $paymentDetailsReply = null;
        $forcedToolNudgeUsed = false;
        $actionKind = mb_strtolower(trim((string) (($reasoning['trace']['action_kind'] ?? '') ?: '')));
        $customerRejectsHandoff = $this->customerRejectsHandoff($incomingMessage);

        // Low confidence is guidance for the model (clarify / try tools), not an automatic
        // human lock — handoff only happens when transfer_to_human (or pending approval) runs.

        for ($i = 0; $i < $maxIterations; $i++) {
            $result = $this->agentChat->completeWithTools(
                messages: $messages,
                tools: $this->tools->openAiDefinitionsForCompany($company),
                company: $company,
                chatId: (int) $chat->id,
            );

            if (! $result->success) {
                break;
            }

            if ($result->toolCalls === []) {
                if ($result->content !== null && trim($result->content) !== '') {
                    if (! $forcedToolNudgeUsed && $this->shouldForceDoActionTool($actionKind, $toolsUsed, $incomingMessage)) {
                        $forcedToolNudgeUsed = true;
                        $messages[] = ['role' => 'assistant', 'content' => trim($result->content)];
                        $messages[] = [
                            'role' => 'user',
                            'content' => $this->forcedDoActionNudge($actionKind, $incomingMessage),
                        ];
                        continue;
                    }

                    $reply = $this->finalizeReply($company, trim($result->content), $cognitiveContext);
                    $this->learningRecorder->recordOpenAiExchange($company, $incomingMessage, $reply, (int) $chat->id);
                    $this->learningRecorder->recordAgentExchange($company, $incomingMessage, $reply, (int) $chat->id);
                    $this->agentMemory->reflectOnTurn((int) $company->id, (int) $chat->id, $toolCallCount, $handoff);
                    $this->logTrust($company, $chat, $cognitiveContext, $reasoning, $toolsUsed, $reply, 'success');

                    return [
                        'reply' => $reply,
                        'route' => 'agent_os',
                        'handoff' => $handoff,
                        'order_flow_reply' => $orderFlowReply,
                    ];
                }
                break;
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $result->content,
                'tool_calls' => array_map(fn ($tc) => [
                    'id' => $tc['id'],
                    'type' => 'function',
                    'function' => ['name' => $tc['name'], 'arguments' => $tc['arguments']],
                ], $result->toolCalls),
            ];

            foreach ($result->toolCalls as $tc) {
                if ($toolCallCount >= $maxToolCalls) {
                    break 2;
                }
                $toolCallCount++;
                $toolsUsed[] = $tc['name'];

                $args = json_decode($tc['arguments'], true);
                if (! is_array($args)) {
                    $args = [];
                }

                if ($tc['name'] === 'transfer_to_human'
                    && $this->shouldBlockHandoff($actionKind, $incomingMessage, $customerRejectsHandoff, $toolsUsed)) {
                    $toolResult = [
                        'handoff' => false,
                        'blocked' => true,
                        'message' => 'Handoff blocked. Complete the customer request with send_order_invoice, share_payment_details, process_order_message, search_orders, or check_delivery_status instead. Only transfer if they clearly insist on a human after tools fail.',
                    ];
                } else {
                    $toolResult = $this->toolRunner->run($tc['name'], $context, $args);
                }

                if ($tc['name'] === 'transfer_to_human' && ($toolResult['handoff'] ?? false)) {
                    $handoff = true;
                }
                if (! empty($toolResult['pending_approval'])) {
                    $handoff = true;
                }
                if ($tc['name'] === 'process_order_message' && ! empty($toolResult['order_flow_reply'])) {
                    $orderFlowReply = (string) $toolResult['order_flow_reply'];
                }
                if ($tc['name'] === 'share_payment_details' && ! empty($toolResult['customer_message'])) {
                    $paymentDetailsReply = (string) $toolResult['customer_message'];
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tc['id'],
                    'name' => $tc['name'],
                    'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        if ($paymentDetailsReply !== null && trim($paymentDetailsReply) !== '') {
            $reply = $this->finalizeReply($company, trim($paymentDetailsReply), $cognitiveContext);
            $this->learningRecorder->recordOpenAiExchange($company, $incomingMessage, $reply, (int) $chat->id);
            $this->learningRecorder->recordAgentExchange($company, $incomingMessage, $reply, (int) $chat->id);
            $this->cognitive->finalizeEpisode((int) $cognitiveContext['episode_id'], [], 'payment_assisted');
            $this->logTrust($company, $chat, $cognitiveContext, $reasoning, $toolsUsed, $reply, 'payment_assisted');

            return [
                'reply' => $reply,
                'route' => 'agent_os_payment',
                'handoff' => false,
                'order_flow_reply' => $orderFlowReply,
            ];
        }

        // Prefer composing a conversational wrap of order-flow facts when tools produced checkout text.
        if ($orderFlowReply !== null && trim($orderFlowReply) !== '') {
            $reply = $this->finalizeReply($company, trim($orderFlowReply), $cognitiveContext);
            $this->learningRecorder->recordOpenAiExchange($company, $incomingMessage, $reply, (int) $chat->id);
            $this->learningRecorder->recordAgentExchange($company, $incomingMessage, $reply, (int) $chat->id);
            $this->cognitive->finalizeEpisode((int) $cognitiveContext['episode_id'], [], 'order_assisted');
            $this->logTrust($company, $chat, $cognitiveContext, $reasoning, $toolsUsed, $reply, 'order_assisted');

            return [
                'reply' => $reply,
                'route' => 'agent_os_order',
                'handoff' => $handoff,
                'order_flow_reply' => $orderFlowReply,
            ];
        }

        if ($handoff) {
            $this->cognitive->finalizeEpisode((int) $cognitiveContext['episode_id'], [], 'handoff');
            $this->logTrust($company, $chat, $cognitiveContext, $reasoning, $toolsUsed, 'handoff', 'handoff');

            return [
                'reply' => "I've connected you with our team — someone will assist you shortly. Thanks for your patience.",
                'route' => 'agent_os_handoff',
                'handoff' => true,
                'order_flow_reply' => null,
            ];
        }

        $this->cognitive->finalizeEpisode((int) $cognitiveContext['episode_id'], [], 'failed');

        return [
            'reply' => null,
            'route' => 'agent_os_failed',
            'handoff' => false,
            'order_flow_reply' => null,
        ];
    }

    /**
     * @param  list<string>  $toolsUsed
     */
    private function shouldForceDoActionTool(string $actionKind, array $toolsUsed, string $incomingMessage): bool
    {
        $lower = mb_strtolower($incomingMessage);
        $needsInvoice = $actionKind === 'send_document'
            || str_contains($lower, 'invoice')
            || str_contains($lower, 'receipt')
            || (str_contains($lower, 'bill') && ! str_contains($lower, 'billing'));
        $needsPay = $actionKind === 'pay'
            || str_contains($lower, 'pay')
            || str_contains($lower, 'till')
            || str_contains($lower, 'payment');

        if ($needsInvoice && ! in_array('send_order_invoice', $toolsUsed, true)) {
            return true;
        }
        if ($needsPay && ! in_array('share_payment_details', $toolsUsed, true)
            && ! in_array('process_order_message', $toolsUsed, true)
            && ! in_array('check_mpesa_payment', $toolsUsed, true)) {
            return true;
        }

        return false;
    }

    private function forcedDoActionNudge(string $actionKind, string $incomingMessage): string
    {
        $lower = mb_strtolower($incomingMessage);
        if ($actionKind === 'send_document' || str_contains($lower, 'invoice') || str_contains($lower, 'receipt')) {
            return 'SYSTEM: You promised or need to fulfill a document request. Call send_order_invoice now. Do not transfer_to_human.';
        }

        return 'SYSTEM: Customer wants payment help. Call share_payment_details now with the real configured options. Do not invent methods or transfer_to_human.';
    }

    private function customerRejectsHandoff(string $incomingMessage): bool
    {
        $lower = mb_strtolower($incomingMessage);

        return str_contains($lower, 'do not transfer')
            || str_contains($lower, "don't transfer")
            || str_contains($lower, 'dont transfer')
            || str_contains($lower, 'no transfer')
            || str_contains($lower, 'no human')
            || str_contains($lower, 'stay with')
            || (str_contains($lower, 'no') && str_contains($lower, 'transfer'));
    }

    /**
     * @param  list<string>  $toolsUsed
     */
    private function shouldBlockHandoff(
        string $actionKind,
        string $incomingMessage,
        bool $customerRejectsHandoff,
        array $toolsUsed,
    ): bool {
        if ($customerRejectsHandoff) {
            return true;
        }

        $lower = mb_strtolower($incomingMessage);
        $wantsPerson = str_contains($lower, 'human')
            || str_contains($lower, 'real person')
            || str_contains($lower, 'talk to someone')
            || str_contains($lower, 'speak to')
            || str_contains($lower, 'representative');
        if ($wantsPerson) {
            return false;
        }

        // Invoice / payment requests must be fulfilled by tools — never hand off instead.
        $needsInvoice = $actionKind === 'send_document'
            || str_contains($lower, 'invoice')
            || str_contains($lower, 'receipt')
            || (str_contains($lower, 'bill') && ! str_contains($lower, 'billing'));
        $needsPay = $actionKind === 'pay'
            || str_contains($lower, 'pay')
            || str_contains($lower, 'till')
            || str_contains($lower, 'payment');

        return $needsInvoice || $needsPay;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(
        Company $company,
        Chat $chat,
        AgentToolContext $context,
        string $incomingMessage,
        string $cognitiveBlock,
    ): array {
        $system = $this->buildAgentSystemPrompt($company, $context, $cognitiveBlock, $incomingMessage);
        $messages = [['role' => 'system', 'content' => $system]];

        $history = Message::query()
            ->where('chat_id', $chat->id)
            ->orderByDesc('id')
            ->limit((int) config('agent.conversation_history_limit', 24))
            ->get(['sender', 'content'])
            ->reverse();

        foreach ($history as $msg) {
            $content = trim((string) $msg->content);
            if ($content !== '') {
                $messages[] = [
                    'role' => $msg->sender === 'customer' ? 'user' : 'assistant',
                    'content' => $content,
                ];
            }
        }

        if ($history->isEmpty() || $history->last()?->content !== $incomingMessage) {
            $messages[] = ['role' => 'user', 'content' => $incomingMessage];
        }

        return $messages;
    }

    private function buildAgentSystemPrompt(
        Company $company,
        AgentToolContext $context,
        string $cognitiveBlock,
        string $incomingMessage,
    ): string {
        $persona = <<<'TEXT'
You are this business's conversational operating system — the main front line with customers.

Understand intent from meaning (any language or phrasing) — never wait for fixed keywords or sample phrases.
Classify each turn: inform vs do. If the customer wants something done (order, pay, send a document, check status, refund, book, remember a preference, talk to a person), execute the matching tool(s) in this turn. Do not only promise to do it.
Your available tools are the full capability surface — pick by what the action needs, not by memorized example sentences.
Prefer completing the action yourself. Use transfer_to_human only when the customer clearly wants a person, risk/policy needs a human, or no tool can fulfill the request.
If they ask for an invoice: call send_order_invoice (do not only promise). If they want to pay: call share_payment_details with real configured options (never invent "payment methods are being set up").
If they say not to transfer, do not call transfer_to_human.
Be fluent and human. Never invent prices, stock, or policies. Never expose tool names, reasoning labels, or confidence scores to the customer.
TEXT;

        $learningSamples = $this->learningService->getSamplesForPrompt($company, $incomingMessage);
        $basePrompt = $this->systemPromptBuilder->build(
            $company,
            $learningSamples,
            null,
            $incomingMessage,
        );

        $parts = array_filter([
            $basePrompt,
            $persona,
            $this->tools->capabilityCatalogForPrompt($company),
            $this->customerIntelligence->build(
                $company,
                $context->customerPhone,
                $context->customerName,
                $incomingMessage,
            ),
            $this->skills->promptAddonsForCompany($company),
            $this->digitalTwin->getForPrompt($company),
            $this->worldModel->getForPrompt($company),
            $this->orgMemory->getForPrompt($company),
            $this->strategicMemory->getForPrompt((int) $company->id),
            $this->businessGoals->getForPrompt($company),
            $this->operatingGuides->getForPrompt($company),
            $this->intentChains->getForPrompt($company, $context->customerPhone),
            $this->customerMemory->getForPrompt((int) $company->id, $context->customerPhone),
            $this->agentMemory->getForPrompt((int) $company->id),
            $this->companyBrain->getForPrompt($company),
            $cognitiveBlock,
        ]);

        return implode("\n\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $cognitiveContext
     */
    private function finalizeReply(Company $company, string $draft, array $cognitiveContext): string
    {
        $critiqueResult = $this->critique->review($company, $draft, $cognitiveContext);
        $reply = $critiqueResult['rewritten'] ?? $draft;
        $reply = $this->replyGuard->guard($company, $reply);
        $this->cognitive->finalizeEpisode(
            (int) $cognitiveContext['episode_id'],
            $critiqueResult,
            $critiqueResult['passed'] ? 'success' : 'critique_revised',
        );

        return $reply;
    }

    /**
     * @param  array<string, mixed>  $cognitiveContext
     * @param  array<string, mixed>  $reasoning
     * @param  list<string>  $toolsUsed
     */
    private function logTrust(
        Company $company,
        Chat $chat,
        array $cognitiveContext,
        array $reasoning,
        array $toolsUsed,
        string $outcomePreview,
        string $outcome,
    ): void {
        $trace = $reasoning['trace'] ?? null;
        $governancePayload = $this->governance->enrichTrustPayload(
            $cognitiveContext['governance'] ?? [],
            $cognitiveContext,
        );

        $this->trust->logDecision(
            companyId: (int) $company->id,
            chatId: (int) $chat->id,
            actionType: 'customer_reply',
            goal: is_array($trace) ? ($trace['chosen_plan'] ?? null) : null,
            reasoningSummary: is_array($trace) ? ($trace['understanding'] ?? null) : null,
            toolsUsed: array_values(array_unique($toolsUsed)),
            dataConsulted: [
                'perception' => $cognitiveContext['perception'] ?? null,
                'sentiment' => $reasoning['sentiment'] ?? null,
                'debate_roles' => array_keys($cognitiveContext['debate'] ?? []),
            ],
            confidence: (float) ($cognitiveContext['confidence'] ?? 0.5),
            outcome: $outcome,
            explainability: array_merge([
                'outcome_preview' => mb_substr($outcomePreview, 0, 200),
                'hypotheses' => is_array($trace) ? ($trace['hypotheses'] ?? []) : [],
            ], $governancePayload),
        );
    }
}
