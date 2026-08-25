<?php

namespace App\Services\Orders;

use App\Models\Company;
use App\Models\Order;
use App\Models\PaymentRecoveryAttempt;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp payment recovery ("abandoned invoice") nudges for unpaid orders, mirroring
 * Take App's automated payment reminders at configurable hour offsets after order creation.
 */
class PaymentRecoveryService
{
    public function __construct(
        protected WhatsAppMessageSenderService $waSender,
    ) {}

    /**
     * Scan unpaid, non-COD, non-spam orders and send the next due recovery attempt for each.
     *
     * @return array{processed: int, sent: int}
     */
    public function processDue(): array
    {
        $processed = 0;
        $sent = 0;

        $orders = Order::query()
            ->whereNotIn('payment_status', ['paid', 'refunded'])
            ->where('spam_flagged', false)
            ->where(function ($q) {
                $q->whereNull('payment_method')->orWhere('payment_method', '!=', 'cod');
            })
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->with(['company.settings', 'company.whatsappAccount', 'chat'])
            ->get();

        foreach ($orders as $order) {
            $company = $order->company;
            $settings = $company?->settings;
            if (! $company || ! $settings || ! $settings->payment_recovery_enabled) {
                continue;
            }

            $processed++;
            if ($this->sendNextDueAttempt($order, $company)) {
                $sent++;
            }
        }

        return ['processed' => $processed, 'sent' => $sent];
    }

    protected function sendNextDueAttempt(Order $order, Company $company): bool
    {
        $settings = $company->settings;
        $offsets = $settings->paymentRecoveryHourOffsets();
        $existingAttempts = $order->paymentRecoveryAttempts()->pluck('attempt_number')->all();

        foreach ($offsets as $index => $hours) {
            $attemptNumber = $index + 1;
            if (in_array($attemptNumber, $existingAttempts, true)) {
                continue;
            }
            if (! $order->created_at || $order->created_at->gt(now()->subHours($hours))) {
                continue;
            }

            return $this->sendAttempt($order, $company, $attemptNumber, $hours);
        }

        return false;
    }

    protected function sendAttempt(Order $order, Company $company, int $attemptNumber, int $hours): bool
    {
        $account = $company->whatsappAccount;
        $phone = $order->customer_phone ?: $order->chat?->customer_phone;

        if (! $account || ! $account->isActive() || ! $phone) {
            PaymentRecoveryAttempt::create([
                'order_id' => $order->id,
                'company_id' => $company->id,
                'attempt_number' => $attemptNumber,
                'hours_after_order' => $hours,
                'channel' => 'whatsapp',
                'status' => 'skipped',
                'sent_at' => null,
            ]);

            return false;
        }

        $payUrl = $order->publicPayUrl();
        $message = "Hi! Your order #{$order->order_number} is still awaiting payment.\n\n"
            .'Total due: '.$order->total."\n\n"
            ."Complete your payment here:\n{$payUrl}\n\n"
            .'Reply if you need help or want to cancel this order.';

        $result = $this->waSender->sendText($account, $phone, $message);

        PaymentRecoveryAttempt::create([
            'order_id' => $order->id,
            'company_id' => $company->id,
            'attempt_number' => $attemptNumber,
            'hours_after_order' => $hours,
            'channel' => 'whatsapp',
            'status' => ($result['success'] ?? false) ? 'sent' : 'failed',
            'sent_at' => ($result['success'] ?? false) ? now() : null,
        ]);

        if (! ($result['success'] ?? false)) {
            Log::warning('PaymentRecoveryService: WhatsApp send failed', [
                'order_id' => $order->id,
                'error' => $result['error'] ?? 'unknown',
            ]);
        }

        return (bool) ($result['success'] ?? false);
    }
}
