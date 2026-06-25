<?php

namespace App\Console\Commands;

use App\Models\ConversationLearningSample;
use App\Services\AI\LearningEmbeddingService;
use Illuminate\Console\Command;

class SyncLearningEmbeddings extends Command
{
    protected $signature = 'learning:sync-embeddings {--company= : Limit to a company ID} {--missing-only : Only samples without embeddings}';

    protected $description = 'Generate embeddings for approved conversation learning samples';

    public function handle(LearningEmbeddingService $embeddings): int
    {
        $query = ConversationLearningSample::query()
            ->where('status', ConversationLearningSample::STATUS_APPROVED);

        if ($this->option('company')) {
            $query->where('company_id', (int) $this->option('company'));
        }
        if ($this->option('missing-only')) {
            $query->whereNull('question_embedding');
        }

        $count = 0;
        $query->orderBy('id')->chunkById(25, function ($samples) use ($embeddings, &$count) {
            foreach ($samples as $sample) {
                $embeddings->syncSample($sample);
                $count++;
            }
        });

        $this->info("Synced embeddings for {$count} learning sample(s).");

        return self::SUCCESS;
    }
}
