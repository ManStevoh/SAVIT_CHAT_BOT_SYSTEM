<?php

namespace App\Jobs\Platform;

use App\Services\Platform\DomainEventDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchDomainEventsJob implements ShouldQueue
{
    use Queueable;

    public function handle(DomainEventDispatcher $dispatcher): void
    {
        $dispatcher->processPending(100);
    }
}
