<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\FlutterwaveService;
use App\Services\MailService;
use App\Services\OrderPaymentService;
use App\Services\Platform\BillingLedgerService;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FlutterwaveWebhookController extends Controller
{
    public function __construct(
        protected FlutterwaveService $flutterwave,
        protected MailService $mailService,
        protected OrderPaymentService $orderPaymentService,
        protected BillingLedgerService $billingLedger,
        protected SubscriptionLifecycleService $lifecycle,
    ) {}

    /**
     * Handle Flutterwave Webhook and Callback.
     */
    public function __invoke(Request $request): JsonResponse|SymfonyResponse
    {
        $status = (string) ($request->input('status') ?? $request->input('data.status') ?? '');
        $txRef = (string) ($request->input('tx_ref') ?? $request->input('data.tx_ref') ?? '');
        $transactionId = (string) ($request->input('transaction_id') ?? $request->input('data.id') ?? '');

        if ($txRef === '' && $transactionId === '') {
            if ($request->expectsJson() || $request->isMethod('POST')) {
                return response()->json(['status' => 'error', 'message' => 'Missing transaction reference'], 400);
            }

            return redirect()->to(url('/'));
        }

        // Webhook signature validation if header present
        $signature = $request->header('verif-hash');
        if ($signature) {
            $expectedHash = $this->flutterwave->getSecretHash();
            if ($expectedHash !== '' && hash_equals($expectedHash, $signature) === false) {
                Log::warning('Flutterwave webhook signature mismatch', ['header' => $signature]);

                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
            }
        }

        // Determine if this is a merchant order or subscription payment
        $configOverride = null;
        $order = null;

        if (str_starts_with($txRef, 'essem_flw_') || str_starts_with($txRef, 'savit_flw_')) {
            if (preg_match('/^(?:essem_flw_|savit_flw_)(\d+)_/', $txRef, $matches)) {
                $orderId = (int) $matches[1];
                $order = Order::with('company.settings')->find($orderId);
                if ($order && $order->company?->settings?->hasOrderPaymentFlutterwaveConfig()) {
                    $configOverride = $order->company->settings->order_payment_flutterwave_config;
                }
            }
        }

        // Verify transaction with Flutterwave API if transaction ID is present
        $verifiedData = [];
        if ($transactionId !== '') {
            $verifyResult = $this->flutterwave->verifyTransaction($transactionId, $configOverride);
            if (! ($verifyResult['success'] ?? false) || ! ($verifyResult['paid'] ?? false)) {
                if (! $request->expectsJson() && $request->isMethod('GET')) {
                    if ($order) {
                        return redirect()->to(url("/pay/{$order->pay_token}"))
                            ->with('error', $verifyResult['error'] ?? 'Payment verification failed.');
                    }

                    return redirect()->to(url('/dashboard/subscription'))
                        ->with('error', $verifyResult['error'] ?? 'Payment verification failed.');
                }

                return response()->json(['status' => 'error', 'message' => 'Transaction not paid'], 400);
            }
            $verifiedData = $verifyResult['data'] ?? [];
        }

        // 1. Process Order Payment if applicable
        if ($order) {
            if ($order->payment_status !== 'paid') {
                $order->update(['payment_method' => 'flutterwave']);
                $this->orderPaymentService->markOrderPaid($order);
            }

            if (! $request->expectsJson() && $request->isMethod('GET')) {
                return redirect()->to(url("/pay/{$order->pay_token}"))
                    ->with('status', 'Payment successful! Thank you for your order.');
            }

            return response()->json(['status' => 'success', 'message' => 'Order payment completed']);
        }

        // 2. Process Subscription Payment if reference starts with sub_flw_ or sub_
        if (str_starts_with($txRef, 'sub_flw_') || str_starts_with($txRef, 'sub_')) {
            $this->processSubscriptionPayment($txRef, $transactionId ?: $txRef, $verifiedData);

            if (! $request->expectsJson() && $request->isMethod('GET')) {
                return redirect()->to(url('/dashboard/subscription?checkout=success'));
            }
        }

        if (! $request->expectsJson() && $request->isMethod('GET')) {
            return redirect()->to(url('/'));
        }

        return response()->json(['status' => 'success', 'message' => 'Callback processed']);
    }

    /**
     * Process subscription payment verification and activation.
     *
     * @param  array<string, mixed>  $flwData
     */
    protected function processSubscriptionPayment(string $txRef, string $transactionId, array $flwData): void
    {
        $parts = explode('_', $txRef);
        $companyId = count($parts) >= 4 ? (int) end($parts) : null;
        if (! $companyId) {
            return;
        }

        $company = Company::find($companyId);
        if (! $company) {
            return;
        }

        $existingPayment = Subscription::where('external_payment_id', $transactionId)->first();
        if ($existingPayment) {
            return;
        }

        $amount = (float) ($flwData['amount'] ?? 0);
        $currency = strtoupper((string) ($flwData['currency'] ?? 'KES'));

        $plan = Plan::where('price_amount', $amount)->first() ?? Plan::where('slug', 'professional')->first() ?? Plan::first();
        $planSlug = $plan?->slug ?? 'professional';

        Subscription::where('company_id', $company->id)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'cancelled']);

        $startDate = now()->format('Y-m-d');
        $endDate = now()->addMonth()->format('Y-m-d');

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => $planSlug,
            'status' => 'active',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'amount' => $amount > 0 ? $amount : ($plan?->price_amount ?? 0),
            'billing_cycle' => 'monthly',
            'payment_method' => 'flutterwave',
            'external_payment_id' => $transactionId,
        ]);

        $this->billingLedger->record(
            'flutterwave',
            $transactionId,
            (float) $subscription->amount,
            $company->id,
            $subscription->id,
            $currency,
            'subscription',
            $txRef,
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
            Log::warning('Flutterwave: failed to send subscription notification: '.$e->getMessage());
        }

        try {
            app(\App\Services\Agent\AgentCommerceProvisioningService::class)->syncForCompany($company->fresh());
        } catch (\Throwable $e) {
            Log::warning('Flutterwave: agent commerce provision failed: '.$e->getMessage());
        }
    }
}
