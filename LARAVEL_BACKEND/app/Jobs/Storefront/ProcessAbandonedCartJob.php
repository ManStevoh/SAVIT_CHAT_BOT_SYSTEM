<?php

namespace App\Jobs\Storefront;

use App\Services\Storefront\AbandonedCartRecoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAbandonedCartJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AbandonedCartRecoveryService $service): void
    {
        $result = $service->processDue();
        if ($result['sent'] > 0) {
            Log::info('Abandoned cart recovery messages sent', $result);
        }
    }
}
