<?php

namespace App\Jobs\Platform;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Platform\DomainEventDispatcher;
use App\Services\Platform\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessTrialTransitionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        DomainEventDispatcher $events,
        NotificationDispatcher $notifications,
    ): void {
        $expiredTrials = Subscription::where('status', 'trial')
            ->where('end_date', '<', now()->toDateString())
            ->get();

        foreach ($expiredTrials as $subscription) {
            $plan = Plan::where('slug', $subscription->plan)->first();
            $action = $plan?->trial_elapsed_action ?? 'downgrade';

            if ($action === 'downgrade') {
                $subscription->update(['status' => 'expired', 'plan' => 'starter']);
            } else {
                $subscription->update(['status' => 'expired']);
            }

            $company = $subscription->company;
            if ($company) {
                $notifications->dispatch($company, 'subscription.expiring', [
                    'plan' => $subscription->plan,
                    'end_date' => $subscription->end_date,
                    'owner_email' => $company->email,
                ]);

                $events->dispatch('subscription.expired', [
                    'subscription_id' => $subscription->id,
                    'plan' => $subscription->plan,
                ], $company->id);
            }

            Log::info('Trial subscription expired', [
                'company_id' => $subscription->company_id,
                'action' => $action,
            ]);
        }
    }
