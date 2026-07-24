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

        if (($cognitiveContext['confidence_action'] ?? '') === 'escalate') {
            $handoff = true;
        }

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

                $toolResult = $this->toolRunner->run($tc['name'], $context, $args);

                if ($tc['name'] === 'transfer_to_human' && ($toolResult['handoff'] ?? false)) {
                    $handoff = true;
                }
                if (! empty($toolResult['pending_approval'])) {
                    $handoff = true;
                }
                if ($tc['name'] === 'process_order_message' && ! empty($toolResult['order_flow_reply'])) {
                    $orderFlowReply = (string) $toolResult['order_flow_reply'];
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tc['id'],
                    'name' => $tc['name'],
                    'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                ];
            }
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

Be fluent and human: listen, remember, reason with facts from tools and context, then answer confidently.
Hold natural sales and support conversations end-to-end (discover → recommend → order → pay → track → resolve).
Use tools for live catalog, FAQ, knowledge, orders, payments, delivery, and memory. Persist important preferences with remember_customer.
Never expose internal reasoning labels, tool names, or confidence scores to the customer.
Never invent prices, stock, or policies. Prefer truth over closing a sale.
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
