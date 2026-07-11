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
