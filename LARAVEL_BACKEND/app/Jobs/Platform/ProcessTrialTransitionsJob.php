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
