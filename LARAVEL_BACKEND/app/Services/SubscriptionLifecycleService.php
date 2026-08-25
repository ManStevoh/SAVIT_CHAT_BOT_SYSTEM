<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionReminderLog;
use App\Services\Platform\NotificationDispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Subscription lifecycle: expiry reminders, expiry transitions, notifications.
 */
class SubscriptionLifecycleService
{
    public function __construct(
        protected MailService $mail,
        protected NotificationDispatcher $notifications,
    ) {}

    /**
     * Send 7/3/1-day reminders with idempotency.
     *
     * @param  list<int>  $daysList
     * @return array{sent: int, skipped: int}
     */
    public function sendExpiryReminders(array $daysList = [7, 3, 1]): array
    {
        $sent = 0;
        $skipped = 0;
        $today = now()->startOfDay();

        foreach ($daysList as $daysLeft) {
            $daysLeft = (int) $daysLeft;
            if ($daysLeft <= 0) {
                continue;
            }

            $targetDate = $today->copy()->addDays($daysLeft);
            $subscriptions = Subscription::with('company')
                ->whereIn('status', ['active', 'trial', 'cancelled'])
                ->whereDate('end_date', $targetDate)
                ->get();

            foreach ($subscriptions as $subscription) {
                $result = $this->sendReminderForSubscription($subscription, $daysLeft);
                if ($result === 'sent') {
                    $sent++;
                } else {
                    $skipped++;
                }
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    public function sendReminderForSubscription(Subscription $subscription, int $daysLeft): string
    {
        $company = $subscription->company;
        if (! $company) {
            return 'skipped';
        }

        $already = SubscriptionReminderLog::where('subscription_id', $subscription->id)
            ->where('days_before', $daysLeft)
            ->whereDate('target_end_date', $subscription->end_date)
            ->where('channel', 'lifecycle')
            ->exists();

        if ($already) {
            return 'skipped';
        }

        $planName = Plan::where('slug', $subscription->plan)->first()?->name ?? ucfirst((string) $subscription->plan);
        $endDateFormatted = $subscription->end_date->format('F j, Y');

        try {
            if ($company->email) {
                $this->mail->sendSubscriptionExpiringSoon(
                    $company->email,
                    $planName,
                    $endDateFormatted,
                    $daysLeft
                );
            }

            $this->notifications->dispatch($company, 'subscription.expiring', [
                'plan' => $planName,
                'end_date' => $endDateFormatted,
                'days_left' => $daysLeft,
                'owner_email' => $company->email,
            ]);

            SubscriptionReminderLog::create([
                'subscription_id' => $subscription->id,
                'company_id' => $company->id,
                'days_before' => $daysLeft,
                'target_end_date' => $subscription->end_date->toDateString(),
                'channel' => 'lifecycle',
                'sent_at' => now(),
            ]);

            return 'sent';
        } catch (Throwable $e) {
            Log::warning('Subscription expiry reminder failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return 'skipped';
        }
    }

    /**
     * Expire paid/active subscriptions past end_date (non-trial handled separately for trial actions).
     *
     * @return int Number expired
     */
    public function expireEndedSubscriptions(): int
    {
        $count = 0;
        $ended = Subscription::with('company')
            ->whereIn('status', ['active', 'cancelled'])
            ->whereDate('end_date', '<', now()->toDateString())
            ->get();

        foreach ($ended as $subscription) {
            if ($subscription->status === 'expired') {
                continue;
            }
            $subscription->update(['status' => 'expired']);
            $count++;

            $company = $subscription->company;
            if (! $company) {
                continue;
            }

            $planName = Plan::where('slug', $subscription->plan)->first()?->name ?? ucfirst((string) $subscription->plan);

            try {
                $this->notifications->dispatch($company, 'subscription.expired', [
                    'plan' => $planName,
                    'end_date' => $subscription->end_date->format('F j, Y'),
                    'owner_email' => $company->email,
                ]);

                if ($company->email) {
                    $this->mail->send(
                        $company->email,
                        '['.config('app.name').'] Your subscription has expired',
                        '<p>Your <strong>'.e($planName).'</strong> subscription ended on <strong>'.e($subscription->end_date->format('F j, Y')).'</strong>.</p><p>Renew anytime from your dashboard to restore access.</p>'
                    );
                }
            } catch (Throwable $e) {
                Log::warning('Subscription expired notification failed: '.$e->getMessage());
            }
        }

        return $count;
    }

    public function notifySubscriptionConfirmed(Company $company, string $planName, string $endDate): void
    {
        try {
            $this->notifications->dispatch($company, 'subscription.confirmed', [
                'plan' => $planName,
                'end_date' => $endDate,
                'owner_email' => $company->email,
            ]);
        } catch (Throwable $e) {
            Log::warning('subscription.confirmed in-app failed: '.$e->getMessage());
        }
    }
}
