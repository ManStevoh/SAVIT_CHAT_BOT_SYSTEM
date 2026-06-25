<?php

namespace App\Services;

use App\Jobs\EmbedLearningSampleJob;
use App\Models\Company;
use App\Models\ConversationLearningSample;
use App\Services\AI\AiLearningConfig;
use App\Services\AI\LearningEmbeddingService;
use App\Services\AI\LearningPiiRedactor;
use App\Services\AI\LearningRerankerService;
use App\Services\AI\VectorCandidateFilter;

/**
 * RAG-style conversation memory: store Q/A pairs, embed questions, hybrid retrieval into prompts.
 */
class ConversationLearningService
{
    public function __construct(
        protected AiLearningConfig $learningConfig,
        protected LearningPiiRedactor $piiRedactor,
        protected LearningEmbeddingService $embeddingService,
        protected LearningRerankerService $reranker,
    ) {}

    public function storeSample(
        int $companyId,
        string $customerMessage,
        string $assistantReply,
        string $source = ConversationLearningSample::SOURCE_OPENAI,
        ?int $chatId = null,
        ?int $messageId = null,
        ?string $language = null,
    ): ?ConversationLearningSample {
        if (! $this->learningConfig->isLearningEnabled()) {
            return null;
        }

        $customerMessage = mb_substr(trim($customerMessage), 0, 2000);
        $assistantReply = mb_substr(trim($assistantReply), 0, 2000);
        $minReply = $this->learningConfig->minReplyLength();

        if ($customerMessage === '' || mb_strlen($assistantReply) < $minReply) {
            return null;
        }

        if ($this->learningConfig->piiRedactionEnabled()) {
            $customerMessage = $this->piiRedactor->redact($customerMessage);
            $assistantReply = $this->piiRedactor->redact($assistantReply);
        }

        $fingerprint = $this->fingerprint($customerMessage);
        $duplicate = ConversationLearningSample::query()
            ->where('company_id', $companyId)
            ->where('question_fingerprint', $fingerprint)
            ->exists();

        if ($duplicate) {
            return null;
        }

        $status = $this->learningConfig->requireLearningReview()
            ? ConversationLearningSample::STATUS_PENDING
            : ConversationLearningSample::STATUS_APPROVED;

        $sample = ConversationLearningSample::create([
            'company_id' => $companyId,
            'customer_message' => $customerMessage,
            'question_fingerprint' => $fingerprint,
            'assistant_reply' => $assistantReply,
            'source' => $source,
            'status' => $status,
            'language' => $language,
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);

        if ($status === ConversationLearningSample::STATUS_APPROVED) {
            EmbedLearningSampleJob::dispatch($sample->id);
        }

        $this->pruneOldSamples($companyId);

        return $sample;
    }

    /**
     * @return array<int, array{id: int, question: string, answer: string, score: float, source: string}>
     */
    public function getSamplesForPrompt(Company $company, ?string $currentMessage = null, ?string $language = null): array
    {
        if (! $this->learningConfig->companyCanLearn($company)) {
            return [];
        }

        $limit = $this->learningConfig->promptSampleLimit();
        $retentionCutoff = now()->subDays($this->learningConfig->retentionDays());

        $query = ConversationLearningSample::query()
            ->where('company_id', $company->id)
            ->where('status', ConversationLearningSample::STATUS_APPROVED)
            ->where('created_at', '>=', $retentionCutoff);

        if ($language !== null && $language !== '') {
            $query->where(function ($q) use ($language) {
                $q->whereNull('language')->orWhere('language', $language);
            });
        }

        $samples = $query
            ->orderByDesc('use_count')
            ->orderByDesc('created_at')
            ->limit($limit * 12)
            ->get();

        if ($samples->isEmpty()) {
            return [];
        }

        $ranked = $this->rankHybrid($samples, $currentMessage, (int) $company->id);
        $ranked = $this->reranker->rerank($ranked);
        $seen = [];
        $result = [];
        $usedIds = [];

        foreach ($ranked as $row) {
            /** @var ConversationLearningSample $sample */
            $sample = $row['sample'];
            $key = $sample->question_fingerprint ?? $this->fingerprint($sample->customer_message);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = [
                'id' => (int) $sample->id,
                'question' => $sample->customer_message,
                'answer' => $sample->assistant_reply,
                'score' => round($row['score'], 3),
                'source' => $sample->source,
            ];
            $usedIds[] = $sample->id;
            if (count($result) >= $limit) {
                break;
            }
        }

        if ($usedIds !== []) {
            ConversationLearningSample::query()
                ->whereIn('id', $usedIds)
                ->get()
                ->each(function (ConversationLearningSample $sample) {
                    $sample->increment('use_count');
                    $sample->update(['last_used_at' => now()]);
                });
        }

        return $result;
    }

    public function getRecentSamplesForPrompt(Company $company, int $limit = 8): array
    {
        return $this->getSamplesForPrompt($company, null);
    }

    public function linkSampleToMessage(int $companyId, int $chatId, string $customerMessage, int $messageId): ?int
    {
        $fingerprint = $this->fingerprint($customerMessage);
        $sample = ConversationLearningSample::query()
            ->where('company_id', $companyId)
            ->where('chat_id', $chatId)
            ->where('question_fingerprint', $fingerprint)
            ->whereNull('message_id')
            ->latest('id')
            ->first();

        if ($sample === null) {
            return null;
        }

        $sample->update(['message_id' => $messageId]);

        return (int) $sample->id;
    }

    public function applyFeedback(ConversationLearningSample $sample, int $feedback): void
    {
        if ($feedback < 0) {
            $sample->increment('negative_feedback_count');
            $sample->update([
                'status' => ConversationLearningSample::STATUS_REJECTED,
                'review_notes' => 'Negative customer/agent feedback',
                'reviewed_at' => now(),
            ]);
        } elseif ($feedback > 0) {
            $sample->increment('positive_feedback_count');
            if ($sample->status === ConversationLearningSample::STATUS_PENDING) {
                $sample->update([
                    'status' => ConversationLearningSample::STATUS_APPROVED,
                    'reviewed_at' => now(),
                ]);
                EmbedLearningSampleJob::dispatch($sample->id);
            }
        }
    }

    /**
     * @return array{dead: int, lowQuality: int, topUsed: int}
     */
    public function qualityStats(int $companyId): array
    {
        $base = ConversationLearningSample::query()
            ->where('company_id', $companyId)
            ->where('status', ConversationLearningSample::STATUS_APPROVED);

        return [
            'dead' => (clone $base)->where('use_count', 0)->count(),
            'lowQuality' => (clone $base)->where('negative_feedback_count', '>', 0)
                ->whereColumn('negative_feedback_count', '>=', 'positive_feedback_count')
                ->count(),
            'topUsed' => (clone $base)->where('use_count', '>=', 5)->count(),
        ];
    }

    public function pruneExpiredForCompany(int $companyId): int
    {
        $cutoff = now()->subDays($this->learningConfig->retentionDays());

        return ConversationLearningSample::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($cutoff) {
                $q->where('created_at', '<', $cutoff)
                    ->orWhere('status', ConversationLearningSample::STATUS_REJECTED);
            })
            ->delete();
    }

    public function embeddingCoveragePercent(int $companyId): int
    {
        $total = ConversationLearningSample::query()
            ->where('company_id', $companyId)
            ->where('status', ConversationLearningSample::STATUS_APPROVED)
            ->count();
        if ($total === 0) {
            return 0;
        }
        $with = ConversationLearningSample::query()
            ->where('company_id', $companyId)
            ->where('status', ConversationLearningSample::STATUS_APPROVED)
            ->whereNotNull('question_embedding')
            ->count();

        return (int) round(($with / $total) * 100);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ConversationLearningSample>  $samples
     * @return array<int, array{sample: ConversationLearningSample, score: float}>
     */
    protected function rankHybrid($samples, ?string $currentMessage, int $companyId): array
    {
        if ($currentMessage === null || trim($currentMessage) === '') {
            return $samples->take($this->learningConfig->promptSampleLimit() * 2)
                ->map(fn (ConversationLearningSample $s) => ['sample' => $s, 'score' => 0.0])
                ->all();
        }

        $queryWords = $this->significantWords(mb_strtolower($currentMessage));
        $queryEmbedding = $this->learningConfig->learningEmbeddingsEnabled()
            ? $this->embeddingService->embedQuery($currentMessage, $companyId)
            : null;
        $minSemantic = $this->learningConfig->learningSemanticMinScore();

        $candidates = VectorCandidateFilter::topLexical(
            $samples->all(),
            $currentMessage,
            fn (ConversationLearningSample $s) => $s->customer_message.' '.$s->assistant_reply,
            80,
        );

        $scored = [];
        foreach ($candidates as $sample) {
            $lexical = 0.0;
            if ($queryWords !== []) {
                $textWords = $this->significantWords(mb_strtolower($sample->customer_message.' '.$sample->assistant_reply));
                $intersect = count(array_intersect($queryWords, $textWords));
                $lexical = $intersect / max(1, count($queryWords));
            }

            $semantic = 0.0;
            if ($queryEmbedding !== null && is_array($sample->question_embedding)) {
                $semantic = $this->embeddingService->similarityScore($queryEmbedding, $sample);
                if ($semantic > 0 && $semantic < $minSemantic) {
                    $semantic = 0.0;
                }
            }

            if ($queryEmbedding !== null && $semantic > 0) {
                $score = (0.4 * $lexical) + (0.6 * $semantic);
            } else {
                $score = $lexical;
            }

            if ($score <= 0 && $lexical <= 0) {
                continue;
            }

            $scored[] = ['sample' => $sample, 'score' => $score];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        if ($scored === []) {
            return $samples->take($this->learningConfig->promptSampleLimit())
                ->map(fn (ConversationLearningSample $s) => ['sample' => $s, 'score' => 0.0])
                ->all();
        }

        return $scored;
    }

    /**
     * @return array<int, string>
     */
    protected function significantWords(string $text): array
    {
        $stop = ['a', 'an', 'the', 'to', 'of', 'in', 'on', 'for', 'is', 'are', 'was', 'were', 'i', 'you', 'we', 'they', 'and', 'or', 'what', 'how', 'when', 'where', 'why'];
        $tokens = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($tokens as $t) {
            $t = mb_strtolower($t);
            if (mb_strlen($t) < 2 || in_array($t, $stop, true)) {
                continue;
            }
            $out[] = $t;
        }

        return array_values(array_unique($out));
    }

    protected function fingerprint(string $question): string
    {
        return hash('xxh128', mb_strtolower(trim($question)));
    }

    protected function pruneOldSamples(int $companyId): void
    {
        $this->pruneExpiredForCompany($companyId);

        $max = $this->learningConfig->maxSamplesPerCompany();
        $count = ConversationLearningSample::where('company_id', $companyId)->count();
        if ($count <= $max) {
            return;
        }

        $toDelete = ConversationLearningSample::query()
            ->where('company_id', $companyId)
            ->orderBy('created_at')
            ->limit($count - $max)
            ->pluck('id');

        ConversationLearningSample::whereIn('id', $toDelete)->delete();
    }
}
