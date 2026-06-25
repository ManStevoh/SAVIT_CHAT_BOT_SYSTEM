<?php

namespace App\Services\AI;

use App\Models\ConversationLearningSample;

class LearningEmbeddingService
{
    public function __construct(
        protected OpenAiClient $openAiClient,
        protected AiLearningConfig $learningConfig,
    ) {}

    public function syncSample(ConversationLearningSample $sample): void
    {
        if (! $this->learningConfig->learningEmbeddingsEnabled()) {
            return;
        }

        $text = $this->embeddingText($sample);
        if ($text === '') {
            return;
        }

        $vector = $this->openAiClient->embedText($text, (int) $sample->company_id);
        if ($vector === null) {
            return;
        }

        $sample->update(['question_embedding' => $vector]);
    }

    public function embeddingText(ConversationLearningSample $sample): string
    {
        $q = trim($sample->customer_message);
        $a = trim(mb_substr($sample->assistant_reply, 0, 400));

        return $a !== '' ? "Customer: {$q}\nAssistant: {$a}" : $q;
    }

    /**
     * @return array<int, float>|null
     */
    public function embedQuery(string $message, int $companyId): ?array
    {
        if (! $this->learningConfig->learningEmbeddingsEnabled()) {
            return null;
        }

        $text = trim($message);
        if ($text === '') {
            return null;
        }

        return $this->openAiClient->embedText($text, $companyId);
    }

    /**
     * @param  array<int, float>  $query
     */
    public function similarityScore(array $query, ConversationLearningSample $sample): float
    {
        $stored = $sample->question_embedding;
        if (! is_array($stored) || $stored === []) {
            return 0.0;
        }

        return VectorSimilarity::cosine($query, $stored);
    }
}
