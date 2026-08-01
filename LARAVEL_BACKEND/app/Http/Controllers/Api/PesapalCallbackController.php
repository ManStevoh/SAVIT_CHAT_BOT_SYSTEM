<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\MailService;
use App\Services\OrderPaymentService;
use App\Services\PesapalService;
use App\Services\Platform\BillingLedgerService;
use App\Services\SubscriptionLifecycleService;
use App\Services\SubscriptionPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PesapalCallbackController extends Controller
{
    public function __construct(
        protected PesapalService $pesapal,
        protected MailService $mailService,
        protected OrderPaymentService $orderPaymentService,
        protected BillingLedgerService $billingLedger,
        protected SubscriptionPricingService $pricing,
        protected SubscriptionLifecycleService $lifecycle,
    ) {}

    /**
     * Handle Pesapal IPN and return callbacks.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $orderTrackingId = (string) ($request->input('OrderTrackingId') ?? $request->input('orderTrackingId') ?? '');
        $merchantReference = (string) ($request->input('OrderMerchantReference') ?? $request->input('orderMerchantReference') ?? '');
        $notificationType = (string) ($request->input('OrderNotificationType') ?? $request->input('orderNotificationType') ?? 'IPNChange');

        if ($orderTrackingId === '') {
            return response()->json([
                'orderNotificationType' => $notificationType,
                'orderTrackingId' => $orderTrackingId,
                'orderMerchantReference' => $merchantReference,
                'status' => '400',
                'message' => 'Missing OrderTrackingId',
            ], 400);
        }

        // Determine if this is a company order payment or a platform subscription payment
        $configOverride = null;
        $order = null;

        if (str_starts_with($merchantReference, 'essem_ord_') || str_starts_with($merchantReference, 'savit_ord_')) {
            if (preg_match('/^(?:essem_ord_|savit_ord_)(\d+)_/', $merchantReference, $matches)) {
                $orderId = (int) $matches[1];
                $order = Order::with('company.settings')->find($orderId);
                if ($order && $order->company?->settings?->hasOrderPaymentPesapalConfig()) {
                    $configOverride = $order->company->settings->order_payment_pesapal_config;
                }
            }
        }

        // Verify status with Pesapal API
        $statusResult = $this->pesapal->getTransactionStatus($orderTrackingId, $configOverride);
        if (! ($statusResult['success'] ?? false)) {
            return response()->json([
                'orderNotificationType' => $notificationType,
                'orderTrackingId' => $orderTrackingId,
                'orderMerchantReference' => $merchantReference,
                'status' => '500',
                'message' => $statusResult['error'] ?? 'Could not verify status',
            ], 500);
        }

        if (! ($statusResult['paid'] ?? false)) {
            return response()->json([
                'orderNotificationType' => $notificationType,
                'orderTrackingId' => $orderTrackingId,
                'orderMerchantReference' => $merchantReference,
                'status' => '200',
            ], 200);
        }

        // 1. Process Order Payment if applicable
        if ($order) {
            if ($order->payment_status !== 'paid') {
                $order->update(['payment_method' => 'pesapal']);
                $this->orderPaymentService->markOrderPaid($order);
            }

            return response()->json([
                'orderNotificationType' => $notificationType,
                'orderTrackingId' => $orderTrackingId,
                'orderMerchantReference' => $merchantReference,
                'status' => '200',
            ], 200);
        }

        // 2. Process Subscription Payment if reference starts with sub_pesapal_ or sub_
        if (str_starts_with($merchantReference, 'sub_pesapal_') || str_starts_with($merchantReference, 'sub_')) {
            $this->processSubscriptionPayment($merchantReference, $orderTrackingId, $statusResult['data'] ?? []);
        }

        return response()->json([
            'orderNotificationType' => $notificationType,
            'orderTrackingId' => $orderTrackingId,
            'orderMerchantReference' => $merchantReference,
            'status' => '200',
        ], 200);
    }

    /**
     * Complete subscription payment after Pesapal confirmation.
     *
     * @param  array<string, mixed>  $pesapalData
     */
    protected function processSubscriptionPayment(string $merchantReference, string $orderTrackingId, array $pesapalData): void
    {
        // Reference format: sub_pesapal_{uniqid}_{company_id}
        $parts = explode('_', $merchantReference);
        $companyId = count($parts) >= 4 ? (int) end($parts) : null;
        if (! $companyId) {
            return;
        }

        $company = Company::find($companyId);
        if (! $company) {
            return;
        }

        // Avoid double processing
        $existingPayment = Subscription::where('external_payment_id', $orderTrackingId)->first();
        if ($existingPayment) {
            return;
        }

        $amount = (float) ($pesapalData['amount'] ?? 0);
        $currency = strtoupper((string) ($pesapalData['currency'] ?? 'KES'));

        // Match plan or fallback to starter/professional
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
            'payment_method' => 'pesapal',
            'external_payment_id' => $orderTrackingId,
        ]);

        $this->billingLedger->record(
            'pesapal',
            $orderTrackingId,
            (float) $subscription->amount,
            $company->id,
            $subscription->id,
            $currency,
            'subscription',
            $merchantReference,
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
            \Illuminate\Support\Facades\Log::warning('Pesapal: failed to send subscription notification: '.$e->getMessage());
        }

        try {
            app(\App\Services\Agent\AgentCommerceProvisioningService::class)->syncForCompany($company->fresh());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Pesapal: agent commerce provision failed: '.$e->getMessage());
        }
    }
}
