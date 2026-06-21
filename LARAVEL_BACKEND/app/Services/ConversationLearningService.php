<?php

namespace App\Services;

use App\Models\ConversationLearningSample;
use App\Models\Company;

/**
 * Stores and retrieves conversation samples for AI prompt enrichment.
 * Enables "learn from conversations" by feeding past Q&A into the system prompt.
 */
class ConversationLearningService
{
    /** Default number of recent samples to inject into the prompt. */
    private const DEFAULT_PROMPT_SAMPLE_LIMIT = 8;

    /** Maximum length per customer message / reply to store (chars). */
    private const MAX_MESSAGE_LENGTH = 2000;

    /**
     * Store a successful AI exchange for future prompt enrichment.
     */
    public function storeSample(
        int $companyId,
        string $customerMessage,
        string $assistantReply,
        string $source = ConversationLearningSample::SOURCE_OPENAI,
        ?int $chatId = null,
        ?int $messageId = null
    ): void {
        $customerMessage = mb_substr(trim($customerMessage), 0, self::MAX_MESSAGE_LENGTH);
        $assistantReply = mb_substr(trim($assistantReply), 0, self::MAX_MESSAGE_LENGTH);
        if ($customerMessage === '' || $assistantReply === '') {
            return;
        }

        ConversationLearningSample::create([
            'company_id' => $companyId,
            'customer_message' => $customerMessage,
            'assistant_reply' => $assistantReply,
            'source' => $source,
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Get recent Q&A samples for a company to inject into the system prompt.
     *
     * @return array<int, array{question: string, answer: string}>
     */
    public function getRecentSamplesForPrompt(Company $company, int $limit = self::DEFAULT_PROMPT_SAMPLE_LIMIT): array
    {
        $samples = ConversationLearningSample::query()
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit($limit * 2)
            ->get();

        $seen = [];
        $result = [];
        foreach ($samples->reverse() as $sample) {
            $key = mb_strtolower(mb_substr($sample->customer_message, 0, 100));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = [
                'question' => $sample->customer_message,
                'answer' => $sample->assistant_reply,
            ];
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }
}
