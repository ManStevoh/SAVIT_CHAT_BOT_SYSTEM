<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\ConversationLearningService;
use Illuminate\Console\Command;

class PruneExpiredLearningSamples extends Command
{
    protected $signature = 'learning:prune-expired {--company= : Limit to a company ID}';

    protected $description = 'Delete conversation learning samples older than the platform retention policy';

    public function handle(ConversationLearningService $learning): int
    {
        $companyId = $this->option('company');
        $total = 0;

        $query = Company::query()->select('id');
        if ($companyId) {
            $query->where('id', (int) $companyId);
        }

        $query->orderBy('id')->chunkById(100, function ($companies) use ($learning, &$total) {
            foreach ($companies as $company) {
                $total += $learning->pruneExpiredForCompany((int) $company->id);
            }
        });

        $this->info("Pruned {$total} expired learning sample(s).");

        return self::SUCCESS;
    }
}
