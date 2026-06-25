<?php

namespace App\Services\Conversation;

use App\Models\Company;
use App\Services\AI\OpenAIConversationBuilder;
use App\Services\AI\OpenAiClient;
use App\Services\AI\ReplyGuardService;

/**
 * Generates guarded OpenAI WhatsApp replies with a single retry on failure.
 */
final class WhatsAppAiReplyGenerator
{
    public function __construct(
        protected OpenAIConversationBuilder $conversationBuilder,
        protected OpenAiClient $openAiClient,
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

        $result = $this->openAiClient->chatCompletion(
            messages: $messages,
            useCase: OpenAiClient::USE_CASE_WHATSAPP,
            companyId: $company->id,
            chatId: $chatId,
            timeoutSeconds: 25,
        );

        if (! $result->success || $result->content === null) {
            $result = $this->openAiClient->chatCompletion(
                messages: $messages,
                useCase: OpenAiClient::USE_CASE_WHATSAPP,
                companyId: $company->id,
                chatId: $chatId,
                temperature: 0.4,
                timeoutSeconds: 30,
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
