<?php

namespace App\Console\Commands;

use App\Jobs\Growth\SyncMetaAdSpendJob;
use App\Jobs\Growth\SyncMetaMetricsJob;
use App\Jobs\Growth\ProcessCrmFollowUpsJob;
use Illuminate\Console\Command;

class GrowthSyncMetaCommand extends Command
{
    protected $signature = 'growth:sync-meta
        {--company= : Limit to company ID}
        {--metrics : Sync post metrics only}
        {--ads : Sync Meta ad spend only}
        {--crm : Run CRM follow-ups only}';

    protected $description = 'Sync Meta metrics, ad spend, and/or CRM follow-ups';

    public function handle(): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;

        if ($this->option('crm')) {
            ProcessCrmFollowUpsJob::dispatchSync($companyId);
            $this->info('CRM follow-ups processed.');

            return self::SUCCESS;
        }

        if ($this->option('metrics') || ! $this->option('ads')) {
            SyncMetaMetricsJob::dispatchSync($companyId);
            $this->info('Meta post metrics synced.');
        }

        if ($this->option('ads') || ! $this->option('metrics')) {
            SyncMetaAdSpendJob::dispatchSync($companyId);
            $this->info('Meta ad spend synced.');
        }

        return self::SUCCESS;
    }
}
