<?php

namespace App\Services\Conversation;

use App\Models\Company;
use App\Services\AI\AiOrchestrator;
use App\Services\AI\AiUseCase;
use App\Services\AI\OpenAIConversationBuilder;
use App\Services\AI\ReplyGuardService;

/**
 * Generates guarded WhatsApp replies via the AI orchestration layer (fast vs full chat).
 */
final class WhatsAppAiReplyGenerator
{
    public function __construct(
        protected OpenAIConversationBuilder $conversationBuilder,
        protected AiOrchestrator $orchestrator,
        protected ReplyGuardService $replyGuard,
        protected ConversationLearningRecorder $learningRecorder,
    ) {}

    public function generate(
        Company $company,
        string $message,
        ?string $customerName,
        ?int $chatId,
        ?string $orderFlowContext = null,
    ): ?string {
        $messages = $this->conversationBuilder->build($company, $message, $customerName, $chatId, $orderFlowContext);

        $result = $this->orchestrator->chat(
            messages: $messages,
            company: $company,
            useCase: AiUseCase::WHATSAPP,
            chatId: $chatId,
            timeoutSeconds: 25,
            latestUserMessage: $message,
        );

        if (! $result->success || $result->content === null) {
            $result = $this->orchestrator->chat(
                messages: $messages,
                company: $company,
                useCase: AiUseCase::WHATSAPP,
                chatId: $chatId,
                temperature: 0.4,
                timeoutSeconds: 30,
                latestUserMessage: $message,
            );
        }

        if (! $result->success || $result->content === null) {
            return null;
        }

        $reply = $this->replyGuard->guard($company, $result->content);
        $this->learningRecorder->recordOpenAiExchange($company, $message, $reply, $chatId);

        return $reply;
    }
}
