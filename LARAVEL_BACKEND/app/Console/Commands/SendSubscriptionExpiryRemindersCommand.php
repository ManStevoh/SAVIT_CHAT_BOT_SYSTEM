<?php

namespace App\Console\Commands;

use App\Services\SubscriptionLifecycleService;
use Illuminate\Console\Command;

class SendSubscriptionExpiryRemindersCommand extends Command
{
    protected $signature = 'subscription:expiry-reminders
                            {--days=7,3,1 : Comma-separated days before expiry to send reminders}
                            {--expire : Also mark ended active/cancelled subscriptions as expired}';

    protected $description = 'Send subscription expiry reminders (email + in-app) and optionally expire ended subscriptions';

    public function __construct(
        protected SubscriptionLifecycleService $lifecycle
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $daysOption = (string) $this->option('days');
        $daysList = array_values(array_filter(
            array_map('intval', explode(',', $daysOption)),
            fn ($d) => $d > 0
        ));
        if ($daysList === []) {
            $daysList = [7, 3, 1];
        }

        $result = $this->lifecycle->sendExpiryReminders($daysList);
        $this->info("Reminders sent: {$result['sent']} (skipped: {$result['skipped']}).");

        if ($this->option('expire')) {
            $expired = $this->lifecycle->expireEndedSubscriptions();
            $this->info("Expired subscriptions marked: {$expired}.");
        }

        return self::SUCCESS;
    }
}
