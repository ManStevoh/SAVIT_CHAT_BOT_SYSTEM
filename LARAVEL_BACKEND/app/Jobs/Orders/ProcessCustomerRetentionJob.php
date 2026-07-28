<?php

namespace App\Jobs\Orders;

use App\Services\Orders\CustomerRetentionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessCustomerRetentionJob implements ShouldQueue
{
    use Queueable;

    public function handle(CustomerRetentionService $service): void
    {
        $service->runDaily();
    }
}
