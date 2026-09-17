<?php

namespace App\Console\Commands;

use App\Jobs\Platform\ProcessPlatformMarketingJob;
use App\Services\PlatformMarketingService;
use Illuminate\Console\Command;

class PlatformMarketingRunCommand extends Command
{
    protected $signature = 'platform:marketing-run {--sync : Run in-process instead of queue}';

    protected $description = 'Send due Super Admin marketing campaigns (scheduled, recurring, trigger)';

    public function handle(PlatformMarketingService $marketing): int
    {
        if ($this->option('sync')) {
            $result = $marketing->processDue();
            $this->info('Processed '.$result['processed'].' campaigns, sent '.$result['sent'].' messages.');

            return self::SUCCESS;
        }

        ProcessPlatformMarketingJob::dispatch();
        $this->info('Queued platform marketing job.');

        return self::SUCCESS;
    }
}
