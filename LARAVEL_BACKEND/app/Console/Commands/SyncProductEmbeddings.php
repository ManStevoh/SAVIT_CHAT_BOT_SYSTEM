<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AI\KnowledgeChunkService;
use Illuminate\Console\Command;

class SyncProductEmbeddings extends Command
{
    protected $signature = 'products:sync-embeddings {--company= : Limit to a company ID} {--missing-only : Only products without catalog_embedding}';

    protected $description = 'Generate catalog embeddings and knowledge chunks for products';

    public function handle(KnowledgeChunkService $chunks): int
    {
        $query = Product::query()->where('status', 'active');

        if ($this->option('company')) {
            $query->where('company_id', (int) $this->option('company'));
        }
        if ($this->option('missing-only')) {
            $query->whereNull('catalog_embedding');
        }

        $count = 0;
        $query->orderBy('id')->chunkById(20, function ($products) use ($chunks, &$count) {
            foreach ($products as $product) {
                $chunks->syncProduct($product);
                $count++;
            }
        });

        $this->info("Synced embeddings for {$count} product(s).");

        return self::SUCCESS;
    }
}
