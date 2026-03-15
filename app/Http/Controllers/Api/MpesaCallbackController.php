<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    public function __construct(
        protected MailService $mailService
    ) {}

    /**
     * Daraja API calls this URL with STK push result. No auth; validate by processing only known structure.
     */
    public function __invoke(Request $request): Response
    {
        $payload = $request->all();

        $stkCallback = $payload['Body']['stkCallback'] ?? null;
        if (! $stkCallback) {
            Log::warning('M-Pesa callback: missing Body.stkCallback');
            return response('OK', 200);
        }

        $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;
        $resultCode = (int) ($stkCallback['ResultCode'] ?? -1);
        $resultDesc = $stkCallback['ResultDesc'] ?? '';

        if ($resultCode !== 0) {
            Log::info('M-Pesa callback: payment not successful', [
                'CheckoutRequestID' => $checkoutRequestId,
                'ResultCode' => $resultCode,
                'ResultDesc' => $resultDesc,
            ]);
            return response('OK', 200);
        }

        $pending = $checkoutRequestId ? Cache::get('mpesa_pending:'.$checkoutRequestId) : null;
        if (! $pending) {
            Log::warning('M-Pesa callback: no pending record for CheckoutRequestID '.$checkoutRequestId);
            return response('OK', 200);
        }

        $companyId = $pending['company_id'] ?? null;
        $planSlug = $pending['plan_slug'] ?? null;
        if (! $companyId || ! $planSlug) {
            return response('OK', 200);
        }

        $company = Company::find($companyId);
        $plan = Plan::where('slug', $planSlug)->first();
        if (! $company || ! $plan) {
            return response('OK', 200);
        }

        $metadata = $stkCallback['CallbackMetadata']['Item'] ?? [];
        $amount = 0;
        $transactionId = '';
        foreach ($metadata as $item) {
            $name = $item['Name'] ?? '';
            $value = $item['Value'] ?? null;
            if ($name === 'Amount') {
                $amount = (float) $value;
            }
            if ($name === 'MpesaReceiptNumber') {
                $transactionId = (string) $value;
            }
        }
        if ($amount <= 0) {
            $amount = (float) $plan->price_amount;
        }

        $startDate = now()->format('Y-m-d');
        $endDate = now()->addMonth()->format('Y-m-d');

        Subscription::where('company_id', $company->id)->where('status', 'active')->update(['status' => 'cancelled']);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan->slug,
            'status' => 'active',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'amount' => $amount,
            'billing_cycle' => 'monthly',
            'payment_method' => 'mpesa',
            'external_payment_id' => $transactionId ?: ('mpesa_'.$checkoutRequestId),
        ]);

        Cache::forget('mpesa_pending:'.$checkoutRequestId);

        try {
            $planName = $plan->name;
            $this->mailService->sendSubscriptionConfirmed(
                $company->email,
                $planName,
                now()->addMonth()->format('F j, Y')
            );
        } catch (\Throwable $e) {
            Log::warning('M-Pesa: failed to send subscription email: '.$e->getMessage());
        }

        return response('OK', 200);
    }
}
