<?php

namespace App\Jobs\Orders;

use App\Services\Orders\PaymentRecoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentRecoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PaymentRecoveryService $service): void
    {
        $result = $service->processDue();
        if ($result['sent'] > 0) {
            Log::info('Payment recovery attempts sent', $result);
        }
    }
}
