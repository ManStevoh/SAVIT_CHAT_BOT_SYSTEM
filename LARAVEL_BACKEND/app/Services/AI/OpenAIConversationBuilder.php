<?php

namespace App\Services\AI;

use App\Models\Company;
use App\Models\Message;
use App\Services\Conversation\CustomerMessageClassifier;
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
        private CustomerMessageClassifier $messageClassifier
    ) {}

    /** Maximum number of history messages to send. */
    private const MAX_HISTORY_MESSAGES = 10;

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
        $learningSamples = [];
        if ($this->shouldUseLearningSamples($company)) {
            $learningSamples = $this->learningService->getRecentSamplesForPrompt($company);
        }

        $systemContent = $this->systemPromptBuilder->build($company, $learningSamples, $orderFlowContext);
        $messages = [['role' => 'system', 'content' => $systemContent]];

        if ($chatId !== null) {
            $this->appendHistory($chatId, $messages);
        }

        $hint = $this->messageClassifier->buildOpenAiHint($currentUserMessage);
        $body = $customerName !== null && $customerName !== ''
            ? "[Customer: {$customerName}]\n\n{$currentUserMessage}"
            : $currentUserMessage;
        $userContent = $hint !== null
            ? "[Guidance]\n{$hint}\n\n[Message]\n{$body}"
            : $body;
        $messages[] = ['role' => 'user', 'content' => $userContent];

        return $messages;
    }

    private function shouldUseLearningSamples(Company $company): bool
    {
        $settings = $company->settings;

        return $settings && ($settings->learn_from_conversations ?? true);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    private function appendHistory(int $chatId, array &$messages): void
    {
        $history = Message::query()
            ->where('chat_id', $chatId)
            ->orderBy('created_at')
            ->latest()
            ->take(self::MAX_HISTORY_MESSAGES)
            ->get()
            ->reverse();

        foreach ($history as $m) {
            $role = $m->sender === 'customer' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $m->content];
        }
    }
}
