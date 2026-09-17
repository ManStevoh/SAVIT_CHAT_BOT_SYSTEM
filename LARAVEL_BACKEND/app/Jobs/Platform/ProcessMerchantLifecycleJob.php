<?php

namespace App\Jobs\Platform;

use App\Services\MerchantLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessMerchantLifecycleJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $userId = null) {}

    public function handle(MerchantLifecycleService $lifecycle): void
    {
        $result = $lifecycle->processDue($this->userId);
        if (($result['sent'] ?? 0) > 0) {
            Log::info('Merchant lifecycle messages sent', $result);
        }
    }
}
