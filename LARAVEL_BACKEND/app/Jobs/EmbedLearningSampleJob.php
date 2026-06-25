<?php

namespace App\Jobs;

use App\Models\ConversationLearningSample;
use App\Services\AI\LearningEmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmbedLearningSampleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $sampleId) {}

    public function handle(LearningEmbeddingService $embeddings): void
    {
        $sample = ConversationLearningSample::find($this->sampleId);
        if ($sample === null) {
            return;
        }

        $embeddings->syncSample($sample);
    }
}
