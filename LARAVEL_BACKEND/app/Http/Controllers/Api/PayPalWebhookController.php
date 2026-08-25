<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\MailService;
use App\Services\OrderPaymentService;
use App\Services\PayPalService;
use App\Services\Platform\BillingLedgerService;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PayPalWebhookController extends Controller
{
    public function __construct(
        protected PayPalService $paypal,
        protected MailService $mailService,
        protected OrderPaymentService $orderPaymentService,
        protected BillingLedgerService $billingLedger,
        protected SubscriptionLifecycleService $lifecycle,
    ) {}

    /**
     * Handle PayPal return callback and IPN/webhook.
     */
    public function __invoke(Request $request): JsonResponse|SymfonyResponse
    {
        $token = (string) ($request->query('token') ?? $request->input('token') ?? ''); // PayPal Order ID in return URL
        $reference = (string) ($request->query('reference') ?? $request->input('reference') ?? '');
        $paypalOrderId = $token;

        // If webhook payload
        $eventType = (string) ($request->input('event_type') ?? '');
        if ($eventType !== '') {
            return $this->handleWebhookEvent($request);
        }

        if ($paypalOrderId === '' && $reference === '') {
            if ($request->expectsJson() || $request->isMethod('POST')) {
                return response()->json(['status' => 'error', 'message' => 'Missing transaction token'], 400);
            }

            return redirect()->to(url('/'));
        }

        // Check if this is a platform subscription payment
        $isSubscription = str_starts_with($reference, 'sub_pp_')
            || $request->query('flow') === 'subscription'
            || Cache::has(PayPalService::CACHE_KEY_SUB_PREFIX.$reference);

        if ($isSubscription) {
            $this->processSubscriptionReturn($paypalOrderId, $reference);

            if (! $request->expectsJson() && $request->isMethod('GET')) {
                return redirect()->to(url('/dashboard/subscription?checkout=success'));
            }

            return response()->json(['status' => 'success', 'message' => 'Subscription activated']);
        }

        // Storefront Order payment
        $completeResult = $this->orderPaymentService->completePayPalReturn($paypalOrderId, $reference);
        $order = $completeResult['order'] ?? null;

        if (! $request->expectsJson() && $request->isMethod('GET')) {
            if ($order && $order->pay_token) {
                if ($completeResult['success'] ?? false) {
                    return redirect()->to(url("/pay/{$order->pay_token}"))
                        ->with('status', 'Payment successful! Thank you for your order.');
                }

                return redirect()->to(url("/pay/{$order->pay_token}"))
                    ->with('error', $completeResult['error'] ?? 'PayPal payment could not be completed.');
            }

            if ($completeResult['success'] ?? false) {
                return redirect()->to(url('/orders/payment-complete?status=success'));
            }

            return redirect()->to(url('/orders/payment-complete?status=error'))
                ->with('error', $completeResult['error'] ?? 'Payment verification failed.');
        }

        if ($completeResult['success'] ?? false) {
            return response()->json(['status' => 'success', 'message' => 'Order payment completed']);
        }

        return response()->json(['status' => 'error', 'message' => $completeResult['error'] ?? 'Payment failed'], 422);
    }

    /**
     * Handle PayPal webhook asynchronous events.
     */
    protected function handleWebhookEvent(Request $request): JsonResponse
    {
        $eventType = (string) $request->input('event_type');
        $resource = $request->input('resource') ?? [];
        $paypalOrderId = (string) ($resource['id'] ?? '');

        Log::info('PayPal webhook received', ['event_type' => $eventType, 'order_id' => $paypalOrderId]);

        if (in_array($eventType, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'], true)) {
            $customId = (string) ($resource['purchase_units'][0]['custom_id'] ?? '');
            $reference = (string) ($resource['purchase_units'][0]['reference_id'] ?? '');

            if (str_starts_with($reference, 'sub_pp_') || str_contains($customId, 'company_')) {
                $this->processSubscriptionReturn($paypalOrderId, $reference);
            } elseif ($paypalOrderId !== '') {
                $this->orderPaymentService->completePayPalReturn($paypalOrderId, $reference);
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Process subscription return capture and activate plan.
     */
    protected function processSubscriptionReturn(string $paypalOrderId, string $reference): void
    {
        $cached = Cache::get(PayPalService::CACHE_KEY_SUB_PREFIX.$reference);
        $companyId = $cached['company_id'] ?? null;
        $planId = $cached['plan_id'] ?? null;

        if (! $companyId && preg_match('/^sub_pp_(\d+)_(\d+)_/', $reference, $matches)) {
            $companyId = (int) $matches[1];
            $planId = (int) $matches[2];
        }

        if (! $companyId) {
            return;
        }

        $company = Company::find($companyId);
        if (! $company) {
            return;
        }

        // Capture PayPal payment
        if ($paypalOrderId !== '') {
            $capture = $this->paypal->captureOrder($paypalOrderId);
            if (! ($capture['success'] ?? false) || ! ($capture['paid'] ?? false)) {
                Log::warning('PayPal subscription capture failed', ['order_id' => $paypalOrderId, 'error' => $capture['error'] ?? null]);
            }
        }

        $plan = $planId ? Plan::find($planId) : null;
        if (! $plan) {
            $plan = Plan::where('slug', 'professional')->first() ?? Plan::first();
        }
        $planSlug = $plan?->slug ?? 'professional';

        $existing = Subscription::where('external_payment_id', $paypalOrderId ?: $reference)->first();
        if ($existing) {
            return;
        }

        Subscription::where('company_id', $company->id)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'cancelled']);

        $currency = $this->paypal->getCurrency();
        $amount = (float) ($cached['amount'] ?? $plan->price_amount ?? 0);

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => $planSlug,
            'status' => 'active',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'amount' => $amount,
            'billing_cycle' => 'monthly',
            'payment_method' => 'paypal',
            'external_payment_id' => $paypalOrderId ?: $reference,
        ]);

        $this->billingLedger->record(
            'paypal',
            $paypalOrderId ?: $reference,
            (float) $subscription->amount,
            $company->id,
            $subscription->id,
            $currency,
            'subscription',
            $reference,
            ['plan' => $planSlug]
        );

        try {
            $planName = $plan?->name ?? ucfirst($planSlug);
            $endFormatted = now()->addMonth()->format('F j, Y');
            if ($company->email) {
                $this->mailService->sendSubscriptionConfirmed(
                    $company->email,
                    $planName,
                    $endFormatted
                );
            }
            $this->lifecycle->notifySubscriptionConfirmed($company, $planName, $endFormatted);
        } catch (\Throwable $e) {
            Log::warning('PayPal: failed to send subscription notification: '.$e->getMessage());
        }

        try {
            app(\App\Services\Agent\AgentCommerceProvisioningService::class)->syncForCompany($company->fresh());
        } catch (\Throwable $e) {
            Log::warning('PayPal: agent commerce provision failed: '.$e->getMessage());
        }

        Cache::forget(PayPalService::CACHE_KEY_SUB_PREFIX.$reference);
    }
}
