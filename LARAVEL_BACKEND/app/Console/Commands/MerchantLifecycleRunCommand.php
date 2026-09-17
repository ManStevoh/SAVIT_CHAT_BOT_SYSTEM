<?php

namespace App\Console\Commands;

use App\Jobs\Platform\ProcessMerchantLifecycleJob;
use App\Services\MerchantLifecycleService;
use Illuminate\Console\Command;

class MerchantLifecycleRunCommand extends Command
{
    protected $signature = 'merchant:lifecycle-run {--user= : Only this user id} {--sync : Run in-process instead of queue}';

    protected $description = 'Send due merchant onboarding and marketing emails/WhatsApp messages';

    public function handle(MerchantLifecycleService $lifecycle): int
    {
        $userId = $this->option('user') ? (int) $this->option('user') : null;

        if ($this->option('sync')) {
            $result = $lifecycle->processDue($userId ?: null);
            $this->info('Processed '.$result['processed'].' merchants, sent '.$result['sent'].' messages.');

            return self::SUCCESS;
        }

        ProcessMerchantLifecycleJob::dispatch($userId ?: null);
        $this->info('Queued merchant lifecycle job'.($userId ? " for user {$userId}" : '').'.');

        return self::SUCCESS;
    }
}
