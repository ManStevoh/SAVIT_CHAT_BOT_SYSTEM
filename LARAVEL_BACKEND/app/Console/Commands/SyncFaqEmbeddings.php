<?php

namespace App\Console\Commands;

use App\Models\Faq;
use App\Services\AI\FaqEmbeddingService;
use Illuminate\Console\Command;

class SyncFaqEmbeddings extends Command
{
    protected $signature = 'faqs:sync-embeddings {--company= : Limit to a company ID}';

    protected $description = 'Generate or refresh OpenAI embeddings for FAQ questions';

    public function handle(FaqEmbeddingService $embeddings): int
    {
        $query = Faq::query()->where('is_active', true);
        if ($this->option('company')) {
            $query->where('company_id', (int) $this->option('company'));
        }

        $count = 0;
        $query->orderBy('id')->chunkById(50, function ($faqs) use ($embeddings, &$count) {
            foreach ($faqs as $faq) {
                $embeddings->syncFaq($faq);
                $count++;
            }
        });

        $this->info("Synced embeddings for {$count} FAQ(s).");

        return self::SUCCESS;
    }
}
