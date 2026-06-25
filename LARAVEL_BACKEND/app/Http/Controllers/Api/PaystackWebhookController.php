<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\MailService;
use App\Services\OrderPaymentService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __construct(
        protected PaystackService $paystack,
        protected MailService $mailService,
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

        $this->handleSubscriptionPayment($reference, $metadata, $data);

        return response('OK', 200);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $data
     */
    protected function handleOrderPayment(string $reference, array $metadata, array $data): void
    {
        $pending = Cache::get(PaystackService::CACHE_KEY_ORDER_PREFIX.$reference);
        $orderId = $pending['order_id'] ?? null;

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
        if (! $this->amountsMatch($paidAmount, (float) $order->total)) {
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

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $data
     */
    protected function handleSubscriptionPayment(string $reference, array $metadata, array $data): void
    {
        if (Subscription::where('external_payment_id', $reference)->exists()) {
            Cache::forget(PaystackService::CACHE_KEY_SUB_PREFIX.$reference);

            return;
        }

        $pending = Cache::get(PaystackService::CACHE_KEY_SUB_PREFIX.$reference);
        if (! $pending) {
            Log::warning('Paystack webhook: no cached subscription for reference '.$reference);

            return;
        }

        $companyId = $pending['company_id'] ?? null;
        $planSlug = $pending['plan_slug'] ?? null;

        if (! $companyId || ! $planSlug) {
            Log::warning('Paystack webhook: incomplete pending subscription for reference '.$reference);

            return;
        }

        $company = Company::find($companyId);
        $plan = Plan::where('slug', $planSlug)->first();
        if (! $company || ! $plan) {
            return;
        }

        $amountSubunit = (int) ($data['amount'] ?? 0);
        $amount = $amountSubunit > 0 ? $amountSubunit / 100 : 0;

        if (! $this->amountsMatch($amount, (float) $plan->price_amount)) {
            Log::warning('Paystack webhook: subscription amount mismatch', [
                'reference' => $reference,
                'paid' => $amount,
                'expected' => (float) $plan->price_amount,
            ]);

            return;
        }

        Subscription::where('company_id', $company->id)->where('status', 'active')->update(['status' => 'cancelled']);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan->slug,
            'status' => 'active',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'amount' => $amount,
            'billing_cycle' => 'monthly',
            'payment_method' => 'paystack',
            'external_payment_id' => $reference,
        ]);

        Cache::forget(PaystackService::CACHE_KEY_SUB_PREFIX.$reference);

        try {
            $this->mailService->sendSubscriptionConfirmed(
                $company->email,
                $company->name,
                $plan->name,
                $amount
            );
        } catch (\Throwable $e) {
            Log::warning('Paystack webhook: subscription email failed: '.$e->getMessage());
        }
    }

    protected function amountsMatch(float $paid, float $expected): bool
    {
        if ($expected <= 0) {
            return $paid > 0;
        }

        return abs($paid - $expected) <= 0.01;
    }
}
