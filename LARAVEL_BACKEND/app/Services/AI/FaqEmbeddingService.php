<?php

namespace App\Services\AI;

use App\Models\Faq;

class FaqEmbeddingService
{
    public function __construct(
        protected OpenAiClient $openAiClient,
        protected AiLearningConfig $learningConfig,
        protected KnowledgeChunkService $chunkService,
    ) {}

    public function syncFaq(Faq $faq): void
    {
        if (! $this->learningConfig->faqEmbeddingsEnabled()) {
            return;
        }

        $this->chunkService->syncFaq($faq);

        $text = trim($faq->question);
        $answerExcerpt = trim(mb_substr((string) $faq->answer, 0, 500));
        if ($text === '') {
            return;
        }
        $payload = $answerExcerpt !== '' ? "Question: {$text}\nAnswer: {$answerExcerpt}" : $text;
        $embedding = $this->openAiClient->embedText($payload, $faq->company_id);
        if ($embedding !== null) {
            $faq->update(['question_embedding' => $embedding]);
        }
    }

    /**
     * @return array{answer: string, faq_id: int, score: float}|null
     */
    public function matchSemantic(Faq $faq, array $messageEmbedding, float $minScore): ?array
    {
        $stored = $faq->question_embedding;
        if (! is_array($stored) || $stored === []) {
            return null;
        }

        $score = VectorSimilarity::cosine($messageEmbedding, $stored);
        if ($score < $minScore) {
            return null;
        }

        return [
            'answer' => $faq->answer,
            'faq_id' => (int) $faq->id,
            'score' => round($score * 100, 2),
        ];
    }

    /**
     * @return array<int, float>|null
     */
    public function embedMessage(string $message, int $companyId): ?array
    {
        if (! $this->learningConfig->faqEmbeddingsEnabled()) {
            return null;
        }

        return $this->openAiClient->embedText(trim($message), $companyId);
    }
}
