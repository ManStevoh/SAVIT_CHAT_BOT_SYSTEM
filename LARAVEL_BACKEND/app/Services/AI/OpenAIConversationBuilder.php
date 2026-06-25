<?php

namespace App\Services\AI;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Services\Conversation\CustomerMessageClassifier;
use App\Services\Conversation\ConversationGreetingService;
use App\Services\ConversationLearningService;

/**
 * Builds the full messages array for OpenAI Chat Completions API.
 * Responsibilities: system prompt + conversation history + current user message.
 */
class OpenAIConversationBuilder
{
    public function __construct(
        private SystemPromptBuilder $systemPromptBuilder,
        private ConversationLearningService $learningService,
        private CustomerMessageClassifier $messageClassifier,
        private ConversationGreetingService $greetingService,
        private AiLearningConfig $learningConfig,
    ) {}

    /** Maximum number of history messages to send. */
    private const MAX_HISTORY_MESSAGES = 20;

    /**
     * Build messages for the API: system + optional history + current user.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function build(
        Company $company,
        string $currentUserMessage,
        ?string $customerName,
        ?int $chatId,
        ?string $orderFlowContext = null
    ): array {
        $replyLanguage = $this->resolveReplyLanguage($company, $chatId);
        $inOrderFlow = $this->isInOrderFlow($chatId);
        $learningSamples = $inOrderFlow
            ? []
            : $this->learningService->getSamplesForPrompt($company, $currentUserMessage, $replyLanguage);

        $budget = $this->learningConfig->maxPromptTokens();
        $reservedForReply = (int) (config('openai.max_tokens', 512) + 256);

        $systemContent = $this->systemPromptBuilder->build(
            $company,
            $learningSamples,
            $orderFlowContext,
            $currentUserMessage,
            $replyLanguage,
        );
        $messages = [['role' => 'system', 'content' => $systemContent]];

        if ($chatId !== null) {
            $this->appendHistory($chatId, $messages, $budget - TokenEstimator::estimateMessages($messages) - $reservedForReply);
        }

        $hint = $this->messageClassifier->buildOpenAiHint($currentUserMessage);
        $safeMessage = $this->sanitizeUserMessage($currentUserMessage);
        $safeName = $this->greetingService->sanitizeName($customerName);
        $body = $safeName !== ''
            ? "[Customer: {$safeName}]\n\n{$safeMessage}"
            : $safeMessage;
        $userContent = $hint !== null
            ? "[Guidance]\n{$hint}\n\n[Message]\n{$body}"
            : $body;
        $messages[] = ['role' => 'user', 'content' => $userContent];

        return $messages;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    private function appendHistory(int $chatId, array &$messages, int $tokenBudget): void
    {
        if ($tokenBudget <= 0) {
            return;
        }

        $history = Message::query()
            ->where('chat_id', $chatId)
            ->orderBy('created_at')
            ->latest()
            ->take(self::MAX_HISTORY_MESSAGES)
            ->get()
            ->reverse();

        $selected = [];
        foreach ($history as $m) {
            $role = $m->sender === 'customer' ? 'user' : 'assistant';
            $entry = ['role' => $role, 'content' => $m->content];
            $candidate = array_merge($messages, $selected, [$entry]);
            if (TokenEstimator::estimateMessages($candidate) > $tokenBudget) {
                break;
            }
            $selected[] = $entry;
        }

        foreach ($selected as $entry) {
            $messages[] = $entry;
        }
    }

    private function sanitizeUserMessage(string $message): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message) ?? '';

        return mb_substr(trim($clean), 0, 4000);
    }

    private function resolveReplyLanguage(Company $company, ?int $chatId): ?string
    {
        $company->loadMissing('settings');
        $settings = $company->settings;

        if ($settings && $settings->reply_in_customer_language === false) {
            return $settings->default_reply_language ?: $this->learningConfig->fallbackLanguage();
        }

        if ($chatId !== null) {
            $chatLang = Chat::query()->where('id', $chatId)->value('detected_language');
            if (is_string($chatLang) && $chatLang !== '') {
                return $chatLang;
            }
        }

        if ($settings?->default_reply_language) {
            return $settings->default_reply_language;
        }

        return $this->learningConfig->fallbackLanguage();
    }

    private function isInOrderFlow(?int $chatId): bool
    {
        if ($chatId === null) {
            return false;
        }

        return Chat::query()
            ->where('id', $chatId)
            ->whereNotNull('conversation_step')
            ->where('conversation_step', '!=', '')
            ->exists();
    }
}
