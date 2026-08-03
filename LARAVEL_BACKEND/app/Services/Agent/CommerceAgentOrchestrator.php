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
use App\Services\AI\AiLearningConfig;
use App\Services\AI\ReplyGuardService;
use App\Services\AI\SystemPromptBuilder;
use App\Services\AI\TokenEstimator;
use App\Services\Conversation\ConversationLearningRecorder;
use App\Services\ConversationLearningService;
use App\Support\MessageSanitizer;
use Illuminate\Support\Facades\Log;

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
        protected AiLearningConfig $learningConfig,
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

        $logger = Log::channel('single');
        try {
            $built = Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/agent-debug.log'),
            ]);
            if ($built) {
                $logger = $built;
            }
        } catch (\Throwable) {
        }

        try {
            $logger->info("========================================================================\n"
                ."[NEW TURN] Chat ID: {$chat->id} | Company ID: {$company->id} | Phone: {$customerPhone} | Name: {$customerName}\n"
                ."Incoming Message: \"{$incomingMessage}\"\n"
                ."Active Conversation Step: \"{$chat->conversation_step}\"");
        } catch (\Throwable) {
        }

        $cognitiveContext = $this->cognitive->processTurn(
            $company, $chat, $customerPhone, $customerName, $incomingMessage,
        );
        $reasoning = $cognitiveContext['reasoning'];

        try {
            $logger->info("Inferred Reasoning:", [
                'action_required' => $reasoning['trace']['action_required'] ?? null,
                'action_kind' => $reasoning['trace']['action_kind'] ?? null,
                'customer_stance' => $reasoning['trace']['customer_stance'] ?? null,
            ]);
        } catch (\Throwable) {
        }

        // Reasoning fallback — when trace is unavailable, inject minimal guidance
        if (($reasoning['trace'] ?? null) === null) {
            $cognitiveContext['prompt_block'] = ($cognitiveContext['prompt_block'] ?? '')
                ."\nReasoning unavailable — rely on customer message, tool results, and business profile. Prefer tools over assumptions.";
        }

        $context = new AgentToolContext($company, $chat, $customerPhone, $customerName, $incomingMessage);
        $messages = $this->buildMessages($company, $chat, $context, $incomingMessage, $cognitiveContext['prompt_block'] ?? '');

        $maxIterations = (int) config('agent.max_loop_iterations', 12);
        $maxToolCalls = (int) config('agent.max_tool_calls_per_turn', 16);
        $toolCallCount = 0;
        $toolsUsed = [];
        $handoff = false;
        $orderFlowReply = null;
        $paymentDetailsReply = null;
        $paymentUrl = null;
        $forcedToolNudgeCount = 0;
        $maxForcedNudges = 2;
        $trace = is_array($reasoning['trace'] ?? null) ? $reasoning['trace'] : [];
        $actionKind = mb_strtolower(trim((string) ($trace['action_kind'] ?? '')));
        $actionRequired = array_key_exists('action_required', $trace)
            ? filter_var($trace['action_required'], FILTER_VALIDATE_BOOLEAN)
            : false;
        $customerStance = mb_strtolower(trim((string) ($trace['customer_stance'] ?? '')));
        $wantsHuman = $actionKind === 'handoff' || $customerStance === 'want_human';
        $rejectsHuman = $customerStance === 'reject_human';

        // Low confidence is guidance for the model (clarify / try tools), not an automatic
        // human lock — handoff only happens when transfer_to_human runs and AI stance allows it.

        $lastLogId = null;

        for ($i = 0; $i < $maxIterations; $i++) {
            $result = $this->agentChat->completeWithTools(
                messages: $messages,
                tools: $this->tools->openAiDefinitionsForCompany($company),
                company: $company,
                chatId: (int) $chat->id,
            );

            if ($result->logId !== null) {
                $lastLogId = $result->logId;
            }

            try {
                $logger->info("AI Completion Iteration {$i}:", [
                    'success' => $result->success,
                    'log_id' => $result->logId,
                    'error' => $result->error,
                    'content' => $result->content,
                    'tool_calls_count' => count($result->toolCalls),
                ]);
            } catch (\Throwable) {
            }

            if (! $result->success) {
                \App\Services\WhatsApp\WhatsAppDebugLogger::error('COMMERCE_AGENT_ITERATION_FAILED', [
                    'company_id' => $company->id,
                    'chat_id' => $chat->id,
                    'iteration' => $i,
                    'error' => $result->error,
                ]);

                break;
            }

            if ($result->toolCalls === []) {
                if ($paymentDetailsReply !== null && trim($paymentDetailsReply) !== '') {
                    $reply = $this->finalizeReply($company, trim($paymentDetailsReply), $cognitiveContext, true);
                    $this->learningRecorder->recordOpenAiExchange($company, $incomingMessage, $reply, (int) $chat->id);
                    $this->learningRecorder->recordAgentExchange($company, $incomingMessage, $reply, (int) $chat->id);
                    $this->cognitive->finalizeEpisode((int) $cognitiveContext['episode_id'], [], 'payment_assisted');
                    $this->logTrust($company, $chat, $cognitiveContext, $reasoning, $toolsUsed, $reply, 'payment_assisted');

                    return [
                        'reply' => $reply,
                        'route' => 'agent_os_payment',
                        'handoff' => false,
                        'order_flow_reply' => $orderFlowReply,
                        'log_id' => $lastLogId,
                    ];
                }

                if ($orderFlowReply !== null && trim($orderFlowReply) !== '') {
                    $reply = $this->finalizeReply($company, trim($orderFlowReply), $cognitiveContext, true);
                    $this->learningRecorder->recordOpenAiExchange($company, $incomingMessage, $reply, (int) $chat->id);
                    $this->learningRecorder->recordAgentExchange($company, $incomingMessage, $reply, (int) $chat->id);
                    $this->cognitive->finalizeEpisode((int) $cognitiveContext['episode_id'], [], 'order_assisted');
                    $this->logTrust($company, $chat, $cognitiveContext, $reasoning, $toolsUsed, $reply, 'order_assisted');

                    return [
                        'reply' => $reply,
                        'route' => 'agent_os_order',
                        'handoff' => $handoff,
                        'order_flow_reply' => $orderFlowReply,
                        'log_id' => $lastLogId,
                    ];
                }

                if ($result->content !== null && trim($result->content) !== '') {
                    // Detect circular stalling replies ("let me know", "just let me know")
                    $isCircularReply = preg_match('/\b(?:let me know|just let me know|feel free to|whenever you\'re ready)\b/iu', trim($result->content));

                    if (($forcedToolNudgeCount < $maxForcedNudges) && ($isCircularReply || $this->shouldForceDoActionTool($actionRequired, $actionKind, $toolsUsed, $wantsHuman, $chat, $incomingMessage))) {
                        $forcedToolNudgeCount++;
                        $messages[] = ['role' => 'assistant', 'content' => trim($result->content)];
                        $nudgeText = $isCircularReply
                            ? 'SYSTEM: Do NOT repeat the offer or ask the customer to confirm again. The customer already said yes. Execute the action NOW with the appropriate tool. If they want to order, call process_order_message. If they want to pay, call share_payment_details.'
                            : $this->forcedDoActionNudge($actionKind);
                        $messages[] = [
                            'role' => 'system',
                            'content' => $nudgeText,
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
                        'log_id' => $lastLogId,
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
                    && $this->shouldBlockHandoff($actionRequired, $actionKind, $wantsHuman, $rejectsHuman, $toolsUsed)) {
                    $toolResult = [
                        'handoff' => false,
                        'blocked' => true,
                        'message' => 'Handoff blocked by dialogue intent. Continue the open customer request with the matching capability tool(s). Only transfer_to_human when customer_stance is want_human.',
                    ];
                } else {
                    $toolResult = $this->toolRunner->run($tc['name'], $context, $args);
                }

                $logger->info("Tool Execution '{$tc['name']}':", [
                    'args' => $args,
                    'result' => $toolResult,
                ]);

                if ($tc['name'] === 'transfer_to_human' && ($toolResult['handoff'] ?? false)) {
                    $handoff = true;
                }
                if (! empty($toolResult['pending_approval'])) {
                    $handoff = true;
                }
                if ($tc['name'] === 'process_order_message' && ! empty($toolResult['order_flow_reply'])) {
                    $orderFlowReply = (string) $toolResult['order_flow_reply'];
                    if (! empty($toolResult['pay_url'])) {
                        $paymentUrl = (string) $toolResult['pay_url'];
                    }
                }
                if ($tc['name'] === 'share_payment_details' && ! empty($toolResult['customer_message'])) {
                    $paymentDetailsReply = (string) $toolResult['customer_message'];
                    if (! empty($toolResult['pay_url'])) {
                        $paymentUrl = (string) $toolResult['pay_url'];
                    }
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
            $reply = $this->finalizeReply($company, trim($paymentDetailsReply), $cognitiveContext, true);
            $this->learningRecorder->recordOpenAiExchange($company, $incomingMessage, $reply, (int) $chat->id);
            $this->learningRecorder->recordAgentExchange($company, $incomingMessage, $reply, (int) $chat->id);
            $this->cognitive->finalizeEpisode((int) $cognitiveContext['episode_id'], [], 'payment_assisted');
            $this->logTrust($company, $chat, $cognitiveContext, $reasoning, $toolsUsed, $reply, 'payment_assisted');

            $logger->info("Ending turn: payment_assisted. Reply: \"{$reply}\"");

            return [
                'reply' => $reply,
                'route' => 'agent_os_payment',
                'handoff' => false,
                'order_flow_reply' => $orderFlowReply,
                'pay_url' => $paymentUrl,
                'log_id' => $lastLogId,
            ];
        }

        // Prefer composing a conversational wrap of order-flow facts when tools produced checkout text.
        if ($orderFlowReply !== null && trim($orderFlowReply) !== '') {
            $reply = $this->finalizeReply($company, trim($orderFlowReply), $cognitiveContext, true);
            $this->learningRecorder->recordOpenAiExchange($company, $incomingMessage, $reply, (int) $chat->id);
            $this->learningRecorder->recordAgentExchange($company, $incomingMessage, $reply, (int) $chat->id);
            $this->cognitive->finalizeEpisode((int) $cognitiveContext['episode_id'], [], 'order_assisted');
            $this->logTrust($company, $chat, $cognitiveContext, $reasoning, $toolsUsed, $reply, 'order_assisted');

            $logger->info("Ending turn: order_assisted. Reply: \"{$reply}\"");

            return [
                'reply' => $reply,
                'route' => 'agent_os_order',
                'handoff' => $handoff,
                'order_flow_reply' => $orderFlowReply,
                'pay_url' => $paymentUrl,
                'log_id' => $lastLogId,
            ];
        }

        if ($handoff) {
            $this->cognitive->finalizeEpisode((int) $cognitiveContext['episode_id'], [], 'handoff');
            $this->logTrust($company, $chat, $cognitiveContext, $reasoning, $toolsUsed, 'handoff', 'handoff');

            $logger->info("Ending turn: handoff.");

            return [
                'reply' => "I've connected you with our team — someone will assist you shortly. Thanks for your patience.",
                'route' => 'agent_os_handoff',
                'handoff' => true,
                'order_flow_reply' => null,
                'log_id' => $lastLogId,
            ];
        }

        $this->cognitive->finalizeEpisode((int) $cognitiveContext['episode_id'], [], 'failed');
        $logger->info("Ending turn: failed.");

        return [
            'reply' => null,
            'route' => 'agent_os_failed',
            'handoff' => false,
            'order_flow_reply' => null,
            'log_id' => $lastLogId,
        ];
    }

    /**
     * Force a tool turn from AI-classified intent only (no customer phrase lists).
     *
     * @param  list<string>  $toolsUsed
     */
    private function shouldForceDoActionTool(
        bool $actionRequired,
        string $actionKind,
        array $toolsUsed,
        bool $wantsHuman,
        ?Chat $chat = null,
        ?string $incomingMessage = null,
    ): bool {
        if ($wantsHuman) {
            return false;
        }

        // If conversation step is active or incoming message is a digit/order selection, force process_order_message if not run
        if (($chat && filled($chat->conversation_step)) || ($incomingMessage && preg_match('/^\d+$|^\d+\s*[a-z0-9]/i', trim($incomingMessage)))) {
            if (! in_array('process_order_message', $toolsUsed, true)) {
                return true;
            }
        }

        if (! $actionRequired) {
            return false;
        }

        $fulfillmentTools = match ($actionKind) {
            'send_document' => ['send_order_invoice'],
            'pay' => ['share_payment_details', 'process_order_message', 'check_mpesa_payment'],
            'create_order' => ['process_order_message', 'share_payment_details'],
            'track', 'lookup' => ['search_orders', 'check_delivery_status', 'check_mpesa_payment', 'get_customer_profile'],
            'refund' => ['issue_order_refund', 'search_orders', 'transfer_to_human'],
            'remember' => ['remember_customer'],
            default => ['process_order_message', 'share_payment_details', 'send_order_invoice', 'search_orders', 'get_business_info'],
        };

        foreach ($fulfillmentTools as $tool) {
            if (in_array($tool, $toolsUsed, true)) {
                return false;
            }
        }

        return true;
    }

    private function forcedDoActionNudge(string $actionKind): string
    {
        return match ($actionKind) {
            'send_document' => 'SYSTEM: Intent is send_document. Call send_order_invoice now and continue the open thread. Do not transfer_to_human.',
            'pay' => 'SYSTEM: Intent is pay. If no unpaid order exists yet, call process_order_message YOURSELF with synthesized checkout text from the thread (e.g. "{qty} x {product}", "done", "confirm") — never ask the customer to type a fixed phrase. Then call share_payment_details with real configured options. Do not invent payment setup or transfer_to_human.',
            'create_order' => 'SYSTEM: Intent is create_order. Call process_order_message YOURSELF with a concrete checkout command synthesized from the prior offer/thread (qty x product, done, confirm). Never ask the customer to type "N x ProductName". Then share_payment_details if unpaid. Do not transfer_to_human.',
            'track', 'lookup' => 'SYSTEM: Intent is lookup/track. Call search_orders or check_delivery_status now. Do not transfer_to_human.',
            default => 'SYSTEM: action_required=true. Execute the matching capability tool for this dialogue turn. Continue smoothly from the bot\'s last offer. Do not transfer_to_human unless customer_stance is want_human.',
        };
    }

    /**
     * Block premature handoff using AI stance/action_kind only — not message keyword lists.
     *
     * @param  list<string>  $toolsUsed
     */
    private function shouldBlockHandoff(
        bool $actionRequired,
        string $actionKind,
        bool $wantsHuman,
        bool $rejectsHuman,
        array $toolsUsed,
    ): bool {
        if ($rejectsHuman) {
            return true;
        }
        if ($wantsHuman) {
            return false;
        }

        // Any do-action intent must not be replaced by handoff.
        if ($actionRequired && $actionKind !== 'handoff' && $actionKind !== 'inform') {
            return true;
        }

        if ($this->shouldForceDoActionTool($actionRequired, $actionKind, $toolsUsed, $wantsHuman)) {
            return true;
        }

        return false;
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

        $sanitizedMessage = MessageSanitizer::sanitize($incomingMessage);
        if ($history->isEmpty() || $history->last()?->content !== $incomingMessage) {
            $messages[] = ['role' => 'user', 'content' => $sanitizedMessage];
        }

        return $messages;
    }

    private function buildAgentSystemPrompt(
        Company $company,
        AgentToolContext $context,
        string $cognitiveBlock,
        string $incomingMessage,
    ): string {
        $storefrontUrl = app(\App\Services\Conversation\ConversationGreetingService::class)->publicStorefrontUrl($company, $context->chat, $context->customerPhone);

        $persona = <<<TEXT
You are this business's conversational operating system — the main front line with customers.

Understand intent from meaning in any language or style — never wait for fixed keywords.
Read the full thread: if you asked a question or offered a next step, interpret the customer's reply as a response to that offer (affirmations, slang, short replies all count).
CRITICAL - CATALOG: When the customer asks to see your catalog, products, menu, or says "show me your catalog", "what do you sell", call process_order_message or get_catalog immediately and present the numbered catalog items to the customer.
CRITICAL - IMAGES: When the customer asks to see images or photos of items in their cart or products (e.g. "can I get an image of the item in my cart?"), output [IMAGE_URL: <url> CAPTION: <caption_text>] or call process_order_message with "images". NEVER output broken raw markdown like ![alt](url).
CRITICAL: When the customer says "yes", "ok", "proceed", "sure", "go ahead", "I want to", or any affirmative — execute the action immediately with the appropriate tool. NEVER reply with "let me know if you're ready" or "just let me know" after a customer has already confirmed. Act, don't ask again.
CUSTOMER STOREFRONT LINK: {$storefrontUrl}
Include this link whenever greeting customers, answering catalog/store queries, or ending cart summaries.
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

        $prompt = implode("\n\n", $parts);

        return $this->trimSystemPromptToTokenBudget($prompt, $this->learningConfig->maxPromptTokens());
    }

    /**
     * @param  array<string, mixed>  $cognitiveContext
     */
    private function finalizeReply(Company $company, string $draft, array $cognitiveContext, bool $skipGuard = false): string
    {
        if ($skipGuard) {
            $this->cognitive->finalizeEpisode(
                (int) $cognitiveContext['episode_id'],
                [],
                'success'
            );

            return $draft;
        }

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
     * Trim the Agent OS system prompt to fit within the token budget,
     * dropping least-critical context blocks from the bottom first.
     */
    private function trimSystemPromptToTokenBudget(string $prompt, int $budget): string
    {
        // Reserve tokens for conversation history + reply + user message
        $historyLimit = (int) config('agent.conversation_history_limit', 24);
        $reserved = 800 + 500 + ($historyLimit * 100);
        $available = $budget - $reserved;

        if ($available <= 0 || TokenEstimator::estimate($prompt) <= $available) {
            return $prompt;
        }

        $lines = explode("\n", $prompt);
        while ($lines !== [] && TokenEstimator::estimate(implode("\n", $lines)) > $available) {
            array_pop($lines);
        }

        Log::info('Agent OS system prompt trimmed to fit token budget', [
            'original_tokens' => TokenEstimator::estimate($prompt),
            'budget' => $available,
        ]);

        return implode("\n", $lines);
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
