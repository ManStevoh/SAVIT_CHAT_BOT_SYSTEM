<?php

namespace App\Services\Workflow;

use App\Contracts\ChannelAdapterInterface;
use App\DTOs\InboundEnvelope;
use App\DTOs\OutboundMessage;
use App\DTOs\WorkflowTransitionResult;
use App\Models\Chat;
use App\Models\Company;
use App\Services\AI\UnifiedIntentClassifierService;
use App\Services\Conversation\ConversationStateHydrator;

final class ConversationalOSPipeline
{
    public function __construct(
        private ConversationStateHydrator $hydrator,
        private UnifiedIntentClassifierService $intentClassifier,
        private WorkflowEngine $workflowEngine,
        private \App\Services\AI\CandidateRetrievalService $candidateRetrievalService,
        private \App\Services\AI\ReadOnlyLlmAssistantService $readOnlyLlmAssistantService,
    ) {}

    public function processTurn(
        Company $company,
        Chat $chat,
        InboundEnvelope $envelope,
        ChannelAdapterInterface $channelAdapter
    ): WorkflowTransitionResult {
        \Illuminate\Support\Facades\Log::withContext([
            'tenant_id' => $company->id,
            'chat_id' => $chat->id,
            'wamid' => $envelope->whatsappMessageId,
        ]);

        $lockKey = "chat_lock:{$company->id}:{$chat->id}";

        $transitionResult = \Illuminate\Support\Facades\Cache::lock($lockKey, 10)->block(3, function () use ($company, $chat, $envelope) {
            // Step 1: Hydrate immutable ConversationState from database Chat record
            $currentState = $this->hydrator->hydrateFromChat($chat);

            // Fast-Path Cost Optimization: Single-digit numeric choice for variant or product selection
            $trimmedMsg = trim($envelope->messageText);
            if (is_numeric($trimmedMsg) && (int) $trimmedMsg >= 1 && (int) $trimmedMsg <= 9) {
                $fastToken = ($currentState->step === \App\Enums\CheckoutStep::SELECTING_VARIANT) ? ('o' . $trimmedMsg) : ('p' . $trimmedMsg);
                $resolvedFast = $this->candidateRetrievalService->resolveToken($company->id, $chat->id, $fastToken, $currentState, $company);
                
                // Fallback: if step is not SELECTING_VARIANT but 'o' token resolves, or vice versa
                if (! is_array($resolvedFast)) {
                    $altToken = str_starts_with($fastToken, 'o') ? ('p' . $trimmedMsg) : ('o' . $trimmedMsg);
                    $resolvedFast = $this->candidateRetrievalService->resolveToken($company->id, $chat->id, $altToken, $currentState, $company);
                    if (is_array($resolvedFast)) {
                        $fastToken = $altToken;
                    }
                }

                if (is_array($resolvedFast)) {
                    $fastIntent = new \App\DTOs\IntentResult(
                        intent: \App\Enums\CommerceIntent::SELECT_OPTION,
                        confidence: 1.0,
                        selectedToken: $fastToken,
                        actionDirective: 'SELECT_OPTION',
                        resolvedProductId: $resolvedFast['product_id'] ?? null,
                        resolvedVariantId: $resolvedFast['variant_id'] ?? null,
                        messageText: $envelope->messageText,
                    );

                    $transitionResult = $this->workflowEngine->handle($currentState, $fastIntent, $company);
                    $this->hydrator->dehydrateToChat($transitionResult->nextState, $chat);
                    return $transitionResult;
                }
            }

            // Fetch recent message history (last 6 messages) for multi-turn NLU context
            $recentMessages = \App\Models\Message::query()
                ->where('chat_id', $chat->id)
                ->orderByDesc('id')
                ->limit(6)
                ->get(['sender', 'content'])
                ->reverse()
                ->map(fn ($m) => ['sender' => $m->sender, 'content' => $m->content])
                ->toArray();

            // Step 1b: Retrieve Candidate Products & Ephemeral Option Tokens (p1, v_red, o1)
            $candidateContext = $this->candidateRetrievalService->retrieveCandidates(
                $company,
                $currentState,
                $envelope->messageText
            );

            // Step 2: Perform Layer 2 NLU Intent Classification with Candidate Tokens
            $intentResult = $this->intentClassifier->classifyState(
                $company,
                $currentState,
                $envelope->messageText,
                $recentMessages,
                $candidateContext
            );

            // Step 2b: Branch Read-Only vs Transactional Execution Paths
            $readOnlyIntents = [
                'ask_location', 'ask_hours', 'ask_availability',
                'ask_recommendation', 'ask_comparison',
                'ask_shipping', 'ask_returns', 'general_chat', 'ask_store_location', 'ask_faq', 'ask_delivery_fee'
            ];

            $intentName = $intentResult->intent->value ?? 'general_chat';
            $isReadOnly = in_array($intentName, $readOnlyIntents, true)
                && ! $intentResult->isExplicitPurchaseIntent()
                && empty($intentResult->selectedToken);

            if ($isReadOnly) {
                // 1. Capture State Hash before execution
                $initialStateHash = StatePreservationGuard::hash($currentState);

                // 2. Execute Read-Only LLM Assistant (Zero DB Writes)
                $replyText = $this->readOnlyLlmAssistantService->generateReply(
                    $company,
                    $currentState,
                    $intentResult,
                    $candidateContext
                );

                // 3. HARD GUARANTEE: Assert ConversationState was NOT mutated
                StatePreservationGuard::assertUnchanged($initialStateHash, $currentState);

                return new WorkflowTransitionResult(
                    nextState: $currentState,
                    executedActions: [],
                    responseSpec: 'read_only_response',
                    customerReply: $replyText
                );
            }

            // Step 2c: Deterministic Token Resolution & Tenant Guarding
            $selectedToken = $intentResult->selectedToken ?? $intentResult->targetVariantToken;
            if ($selectedToken) {
                $resolved = $this->candidateRetrievalService->resolveToken($company->id, $chat->id, $selectedToken, $currentState, $company);
                if (is_array($resolved)) {
                    $intentResult->resolvedProductId = $resolved['product_id'] ?? null;
                    $intentResult->resolvedVariantId = $resolved['variant_id'] ?? null;
                } else {
                    // LLM hallucinated token or token from stale state
                    $intentResult->requiresClarification = true;
                    $intentResult->clarificationQuestion = "I didn't quite catch that choice. Please reply with the option number or name!";
                }
            }

            if (! $intentResult->resolvedProductId && $intentResult->targetProductToken) {
                $resolvedP = $this->candidateRetrievalService->resolveToken($company->id, $chat->id, $intentResult->targetProductToken, $currentState, $company);
                if (is_array($resolvedP) && ! empty($resolvedP['product_id'])) {
                    $intentResult->resolvedProductId = $resolvedP['product_id'];
                }
            }

            // Step 3: Run Layer 3 Deterministic Workflow State Machine Engine
            $transitionResult = $this->workflowEngine->handle($currentState, $intentResult, $company);

            // Step 4: Dehydrate and persist updated ConversationState back to DB
            $this->hydrator->dehydrateToChat($transitionResult->nextState, $chat);

            return $transitionResult;
        });

        // Step 5: Send state-driven outbound message via Layer 1 Channel Adapter
        if ($transitionResult->customerReply !== null && trim($transitionResult->customerReply) !== '') {
            $outboundMessage = new OutboundMessage(
                channelType: $channelAdapter->channelName(),
                recipientId: $envelope->externalSenderId,
                companyId: $company->id,
                content: $transitionResult->customerReply,
                responseSpec: $transitionResult->responseSpec,
                extra: $transitionResult->extra
            );

            $channelAdapter->sendOutbound($outboundMessage);
        }

        return $transitionResult;
    }
}
