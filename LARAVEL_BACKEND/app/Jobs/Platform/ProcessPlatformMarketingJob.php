<?php

namespace App\Jobs\Platform;

use App\Services\PlatformMarketingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessPlatformMarketingJob implements ShouldQueue
{
    use Queueable;

    public function handle(PlatformMarketingService $marketing): void
    {
        $result = $marketing->processDue();
        if (($result['sent'] ?? 0) > 0) {
            Log::info('Platform marketing messages sent', $result);
        }
    }
}
