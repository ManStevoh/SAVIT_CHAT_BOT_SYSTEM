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
        \App\Services\WhatsApp\WhatsAppDebugLogger::info('AI_REPLY_GENERATOR_START', [
            'company_id' => $company->id,
            'chat_id' => $chatId,
            'customer_name' => $customerName,
            'message_preview' => mb_substr($message, 0, 100),
        ]);

        $messages = $this->conversationBuilder->build($company, $message, $customerName, $chatId, $orderFlowContext);

        $result = $this->orchestrator->chat(
            messages: $messages,
            company: $company,
            useCase: AiUseCase::WHATSAPP,
            chatId: $chatId,
            temperature: 0.3,
            timeoutSeconds: 25,
            latestUserMessage: $message,
        );

        if (! $result->success || $result->content === null) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::warning('AI_REPLY_GENERATOR_PRIMARY_FAILED_RETRYING', [
                'company_id' => $company->id,
                'chat_id' => $chatId,
                'error' => $result->error,
            ]);
            $result = $this->orchestrator->chat(
                messages: $messages,
                company: $company,
                useCase: AiUseCase::WHATSAPP,
                chatId: $chatId,
                temperature: 0.3,
                timeoutSeconds: 30,
                latestUserMessage: $message,
            );
        }

        if (! $result->success || $result->content === null) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::error('AI_REPLY_GENERATOR_RETRY_FAILED', [
                'company_id' => $company->id,
                'chat_id' => $chatId,
                'error' => $result->error,
            ]);

            return null;
        }

        $reply = $this->replyGuard->guard($company, $result->content);
        $this->learningRecorder->recordOpenAiExchange($company, $message, $reply, $chatId);

        \App\Services\WhatsApp\WhatsAppDebugLogger::info('AI_REPLY_GENERATOR_SUCCESS', [
            'company_id' => $company->id,
            'chat_id' => $chatId,
            'guarded_reply_preview' => mb_substr($reply, 0, 150),
        ]);

        return $reply;
    }
}
