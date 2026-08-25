<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\PaystackService;
use App\Services\PaystackSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __construct(
        protected PaystackService $paystack,
        protected PaystackSubscriptionService $subscriptions,
        protected OrderPaymentService $orderPaymentService
    ) {}

    /**
     * Handle Paystack webhook. No auth; signature verified with secret key.
     */
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('x-paystack-signature', '');

        if (! $this->paystack->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Paystack webhook: invalid signature');

            return response('Invalid signature', 400);
        }

        $event = json_decode($payload, true);
        if (! is_array($event)) {
            return response('Invalid payload', 400);
        }

        $eventType = $event['event'] ?? '';
        if ($eventType !== 'charge.success') {
            return response('OK', 200);
        }

        $data = $event['data'] ?? [];
        $reference = (string) ($data['reference'] ?? '');
        if ($reference === '') {
            return response('OK', 200);
        }

        $metadata = $data['metadata'] ?? [];
        $type = is_array($metadata) ? ($metadata['type'] ?? '') : '';

        if ($type === 'order' || str_starts_with($reference, 'essem_ord_') || str_starts_with($reference, 'savit_ord_')) {
            $this->handleOrderPayment($reference, $metadata, $data);

            return response('OK', 200);
        }

        $result = $this->subscriptions->activateFromCharge(
            $data,
            (string) ($data['id'] ?? $reference)
        );

        if (! ($result['success'] ?? false)) {
            Log::warning('Paystack webhook: subscription activation failed', [
                'reference' => $reference,
                'message' => $result['message'] ?? null,
            ]);
        }

        return response('OK', 200);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $data
     */
    protected function handleOrderPayment(string $reference, array $metadata, array $data): void
    {
        $pending = Cache::get(PaystackService::CACHE_KEY_ORDER_PREFIX.$reference);
        $orderId = $pending['order_id'] ?? ($metadata['order_id'] ?? null);

        if (! $orderId) {
            Log::warning('Paystack webhook: no cached order for reference '.$reference);

            return;
        }

        $order = Order::find($orderId);
        if (! $order || $order->payment_status === 'paid') {
            Cache::forget(PaystackService::CACHE_KEY_ORDER_PREFIX.$reference);

            return;
        }

        $amountSubunit = (int) ($data['amount'] ?? 0);
        $paidAmount = $amountSubunit > 0 ? $amountSubunit / 100 : 0;
        if (! $this->subscriptions->amountsMatch($paidAmount, (float) $order->total)) {
            Log::warning('Paystack webhook: order amount mismatch', [
                'reference' => $reference,
                'paid' => $paidAmount,
                'expected' => (float) $order->total,
            ]);

            return;
        }

        $this->orderPaymentService->markOrderPaid($order);
        Cache::forget(PaystackService::CACHE_KEY_ORDER_PREFIX.$reference);
    }
}
