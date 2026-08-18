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

            $trimmedMsg = trim($envelope->messageText);

            $isCheckoutActiveStep = in_array($currentState->step, [
                \App\Enums\CheckoutStep::COLLECTING_ADDRESS,
                \App\Enums\CheckoutStep::REVIEWING_ORDER,
                \App\Enums\CheckoutStep::SELECTING_PAYMENT_METHOD,
                \App\Enums\CheckoutStep::PROVIDING_PHONE,
                \App\Enums\CheckoutStep::SELECTING_VARIANT,
            ], true);

            if (is_numeric($trimmedMsg) && (int) $trimmedMsg >= 1 && (int) $trimmedMsg <= 99) {
                // Quick Menu choices 1, 2, 3 deterministically route ONLY when NOT in active checkout steps
                if (! $isCheckoutActiveStep && in_array($trimmedMsg, ['1', '2', '3'], true)) {
                    $quickIntent = match ($trimmedMsg) {
                        '1' => new \App\DTOs\IntentResult(intent: \App\Enums\CommerceIntent::ASK_PRODUCT_INFO, confidence: 1.0, messageText: '1'),
                        '2' => new \App\DTOs\IntentResult(intent: \App\Enums\CommerceIntent::ASK_ORDER_STATUS, confidence: 1.0, messageText: '2'),
                        '3' => new \App\DTOs\IntentResult(intent: \App\Enums\CommerceIntent::REQUEST_HUMAN, confidence: 1.0, messageText: '3'),
                    };

                    $transitionResult = $this->workflowEngine->handle($currentState, $quickIntent, $company);
                    $this->hydrator->dehydrateToChat($transitionResult->nextState, $chat);

                    foreach ($transitionResult->executedActions as $action) {
                        if (($action['type'] ?? '') === 'RequestAgentHandoff') {
                            $chat->update([
                                'agent_handling_at' => now(),
                                'ai_handled' => false,
                                'status' => 'pending',
                            ]);
                            break;
                        }
                    }

                    return $transitionResult;
                }

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
                'ask_shipping', 'ask_returns', 'general_chat', 'ask_store_location', 'ask_faq', 'ask_delivery_fee',
                'ask_product_info', 'ask_price', 'unknown'
            ];

            $lowerText = mb_strtolower(trim($envelope->messageText));
            $hasTransactionalKeyword = preg_match('/\b(add|buy|order|checkout|remove|delete|drop|minus|clear|confirm|yes|proceed|continue|done|track)\b/i', $lowerText) === 1 || $lowerText === '2';

            // Automatic Catalog Product Resolution
            if (empty($intentResult->resolvedProductId) && $lowerText !== 'prices' && $lowerText !== 'menu' && $lowerText !== 'catalog') {
                $domainDispatcher = app(\App\Services\Workflow\DomainServiceDispatcher::class);
                $targetStr = $intentResult->product ?? $envelope->messageText;
                $matchedProduct = $domainDispatcher->findProduct($company, $targetStr);
                if (! $matchedProduct && $intentResult->product && $intentResult->product !== $envelope->messageText) {
                    $matchedProduct = $domainDispatcher->findProduct($company, $envelope->messageText);
                }
                if ($matchedProduct) {
                    $intentResult->resolvedProductId = $matchedProduct->id;
                    $intentResult->intent = \App\Enums\CommerceIntent::ADD_TO_CART;
                }
            }

            $isCheckoutActiveStep = in_array($currentState->step, [
                \App\Enums\CheckoutStep::COLLECTING_ADDRESS,
                \App\Enums\CheckoutStep::REVIEWING_ORDER,
                \App\Enums\CheckoutStep::SELECTING_PAYMENT_METHOD,
                \App\Enums\CheckoutStep::PROVIDING_PHONE,
                \App\Enums\CheckoutStep::SELECTING_VARIANT,
            ], true);

            $intentName = $intentResult->intent->value ?? 'general_chat';
            $isReadOnly = (! $isCheckoutActiveStep)
                && in_array($intentName, $readOnlyIntents, true)
                && $intentName !== 'ask_order_status'
                && ! $intentResult->isExplicitPurchaseIntent()
                && empty($intentResult->resolvedProductId)
                && empty($intentResult->selectedToken)
                && ! $hasTransactionalKeyword;

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
            if (in_array($trimmedMsg, ['1', '2', '3'], true) || in_array(mb_strtolower($trimmedMsg), ['prices', 'order', 'track order', 'track', 'talk to agent'], true)) {
                $intentResult->selectedToken = null;
                $intentResult->targetProductToken = null;
                $intentResult->targetVariantToken = null;
                $intentResult->resolvedProductId = null;
                $intentResult->resolvedVariantId = null;
            }

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

            foreach ($transitionResult->executedActions as $action) {
                if (($action['type'] ?? '') === 'RequestAgentHandoff') {
                    $chat->update([
                        'agent_handling_at' => now(),
                        'ai_handled' => false,
                        'status' => 'pending',
                    ]);
                    break;
                }
            }

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
                extra: $transitionResult->extra,
                ctaUrl: $transitionResult->payUrl ?? $transitionResult->extra['cta_url'] ?? null,
                ctaButtonText: $transitionResult->ctaButtonText ?? $transitionResult->extra['cta_button_text'] ?? null,
            );

            $channelAdapter->sendOutbound($outboundMessage);
        }

        return $transitionResult;
    }
}
