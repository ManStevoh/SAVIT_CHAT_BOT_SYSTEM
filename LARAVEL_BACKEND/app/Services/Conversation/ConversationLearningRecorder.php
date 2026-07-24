<?php

namespace App\Services\Conversation;

use App\Models\Company;
use App\Models\Chat;
use App\Models\ConversationLearningSample;
use App\Services\AI\AiLearningConfig;
use App\Services\ConversationLearningService;

/**
 * Records learning samples when platform policy allows (OpenAI, FAQ, agent paths).
 */
final class ConversationLearningRecorder
{
    public function __construct(
        protected ConversationLearningService $learningService,
        protected AiLearningConfig $learningConfig,
    ) {}

    public function recordOpenAiExchange(
        Company $company,
        string $customerMessage,
        string $assistantReply,
        ?int $chatId = null,
    ): void {
        if (! $this->learningConfig->companyCanLearn($company)) {
            return;
        }

        $this->learningService->storeSample(
            $company->id,
            $customerMessage,
            $assistantReply,
            ConversationLearningSample::SOURCE_OPENAI,
            $chatId,
            null,
            $this->languageForChat($chatId),
        );
    }

    public function recordFaqExchange(
        Company $company,
        string $customerMessage,
        string $faqAnswer,
        ?int $chatId = null,
    ): void {
        if (! $this->learningConfig->isLearningEnabled() || ! $this->learningConfig->storeFaqExchanges()) {
            return;
        }

        $this->learningService->storeSample(
            $company->id,
            $customerMessage,
            $faqAnswer,
            ConversationLearningSample::SOURCE_FAQ,
            $chatId,
            null,
            $this->languageForChat($chatId),
        );
    }

    public function recordAgentExchange(
        Company $company,
        string $customerMessage,
        string $agentReply,
        ?int $chatId = null,
    ): void {
        if (! $this->learningConfig->companyCanLearn($company)) {
            return;
        }
        if (! $this->learningConfig->storeAgentReplies()) {
            return;
        }

        $this->learningService->storeSample(
            $company->id,
            $customerMessage,
            $agentReply,
            ConversationLearningSample::SOURCE_AGENT,
            $chatId,
            null,
            $this->languageForChat($chatId),
        );
    }

    private function languageForChat(?int $chatId): ?string
    {
        if ($chatId === null) {
            return null;
        }

        return Chat::query()->where('id', $chatId)->value('detected_language');
    }
}
