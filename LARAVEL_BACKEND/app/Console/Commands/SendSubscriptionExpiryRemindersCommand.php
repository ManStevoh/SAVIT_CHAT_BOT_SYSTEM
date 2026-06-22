<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\MailService;
use Illuminate\Console\Command;

class SendSubscriptionExpiryRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:expiry-reminders
                            {--days=7,3 : Comma-separated days before expiry to send reminders (e.g. 7,3)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email reminders to companies whose subscription is about to expire';

    public function __construct(
        protected MailService $mailService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $daysOption = $this->option('days');
        $daysList = array_map('intval', array_filter(explode(',', $daysOption), fn ($d) => $d > 0));
        if (empty($daysList)) {
            $daysList = [7, 3];
        }

        $today = now()->startOfDay();
        $sent = 0;

        foreach ($daysList as $daysLeft) {
            $targetDate = $today->copy()->addDays($daysLeft);
            $subscriptions = Subscription::with('company')
                ->whereIn('status', ['active', 'trial'])
                ->whereDate('end_date', $targetDate)
                ->get();

            foreach ($subscriptions as $subscription) {
                $company = $subscription->company;
                if (! $company?->email) {
                    continue;
                }

                $planName = Plan::where('slug', $subscription->plan)->first()?->name ?? ucfirst($subscription->plan);
                $endDateFormatted = $subscription->end_date->format('F j, Y');

                try {
                    $this->mailService->sendSubscriptionExpiringSoon(
                        $company->email,
                        $planName,
                        $endDateFormatted,
                        $daysLeft
                    );
                    $sent++;
                    $this->info("Sent expiry reminder to {$company->email} (expires in {$daysLeft} days).");
                } catch (\Throwable $e) {
                    $this->error("Failed to send to {$company->email}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Done. Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
