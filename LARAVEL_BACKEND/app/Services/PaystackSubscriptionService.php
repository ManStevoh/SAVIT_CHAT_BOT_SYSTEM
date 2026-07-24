<?php

namespace App\Services;

use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Platform\BillingLedgerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paystack subscription checkout + activation (one-time charge → local monthly period).
 */
class PaystackSubscriptionService
{
    public function __construct(
        protected PaystackService $paystack,
        protected BillingLedgerService $billingLedger,
        protected MailService $mailService,
        protected SubscriptionPricingService $pricing,
        protected SubscriptionLifecycleService $lifecycle,
    ) {}

    /**
     * Persist a durable pending checkout (survives cache eviction / delayed webhooks).
     *
     * @return array{reference: string, authorization_url: string, amount?: float, discount_amount?: float, original_amount?: float, currency?: string, coupon?: string|null}|array{error: string}
     */
    public function initializeCheckout(
        Company $company,
        Plan $plan,
        string $email,
        ?string $callbackUrl = null,
        ?string $couponCode = null,
    ): array {
        $currency = strtoupper($this->paystack->getCurrency());
        $quote = $this->pricing->quote($plan, $company, $couponCode, $currency);
        if (! ($quote['success'] ?? false)) {
            return ['error' => $quote['message'] ?? 'Could not price this plan.'];
        }

        $amount = (float) $quote['final_amount'];
        $original = (float) $quote['original_amount'];
        $discount = (float) $quote['discount_amount'];

        if ($callbackUrl && ! $this->isAllowedCallbackUrl($callbackUrl)) {
            return ['error' => 'Invalid callback URL.'];
        }

        $reference = 'essem_sub_'.$company->id.'_'.uniqid();
        $resolvedCallback = $callbackUrl
            ?? rtrim((string) config('app.frontend_url', config('app.url')), '/').'/dashboard/subscription?checkout=success';

        $metadata = [
            'company_id' => $company->id,
            'plan_slug' => $plan->slug,
            'type' => 'subscription',
            'original_amount' => $original,
            'discount_amount' => $discount,
            'final_amount' => $amount,
        ];
        if (! empty($quote['code'])) {
            $metadata['coupon_code'] = $quote['code'];
        }

        $result = $this->paystack->initializeTransaction(
            $email,
            $this->paystack->amountToSubunit($amount),
            $reference,
            $resolvedCallback,
            $metadata
        );

        if (! empty($result['error']) || empty($result['authorization_url'])) {
            return ['error' => $result['error'] ?? 'Paystack checkout failed'];
        }

        $reference = (string) ($result['reference'] ?? $reference);

        BillingPayment::updateOrCreate(
            [
                'gateway' => 'paystack',
                'external_event_id' => 'paystack_pending:'.$reference,
            ],
            [
                'company_id' => $company->id,
                'subscription_id' => null,
                'external_payment_id' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'pending',
                'payment_type' => 'subscription',
                'metadata' => [
                    'company_id' => $company->id,
                    'plan_slug' => $plan->slug,
                    'plan_id' => $plan->id,
                    'original_amount' => $original,
                    'discount_amount' => $discount,
                    'coupon_code' => $quote['code'] ?? null,
                ],
                'paid_at' => null,
            ]
        );

        if (! empty($quote['offer'])) {
            $this->pricing->reserveRedemption(
                $quote['offer'],
                $company,
                $reference,
                $original,
                $discount,
                $amount,
                $currency
            );
        }

        Cache::put(PaystackService::CACHE_KEY_SUB_PREFIX.$reference, [
            'company_id' => $company->id,
            'plan_slug' => $plan->slug,
            'expected_amount' => $amount,
        ], now()->addHours(24));

        return [
            'reference' => $reference,
            'authorization_url' => $result['authorization_url'],
            'amount' => $amount,
            'original_amount' => $original,
            'discount_amount' => $discount,
            'currency' => $currency,
            'coupon' => $quote['code'] ?? null,
        ];
    }

    /**
     * Activate subscription from a successful Paystack charge payload.
     *
     * @param  array<string, mixed>  $data  Paystack `data` object from webhook or verify
     * @return array{success: bool, subscription?: Subscription, message?: string, already_processed?: bool}
     */
    public function activateFromCharge(array $data, ?string $eventId = null): array
    {
        $reference = (string) ($data['reference'] ?? '');
        if ($reference === '') {
            return ['success' => false, 'message' => 'Missing payment reference.'];
        }

        $existing = Subscription::where('external_payment_id', $reference)->first();
        if ($existing) {
            $this->forgetPending($reference);

            return ['success' => true, 'subscription' => $existing, 'already_processed' => true];
        }

        $pending = $this->resolvePending($reference, $data['metadata'] ?? []);
        if (! $pending) {
            return ['success' => false, 'message' => 'No pending Paystack subscription for this reference.'];
        }

        $company = Company::find($pending['company_id'] ?? null);
        $plan = Plan::where('slug', $pending['plan_slug'] ?? '')->first();
        if (! $company || ! $plan) {
            return ['success' => false, 'message' => 'Company or plan not found for payment.'];
        }

        $amountSubunit = (int) ($data['amount'] ?? 0);
        $amount = $amountSubunit > 0 ? $amountSubunit / 100 : 0;

        $pendingPayment = BillingPayment::where('gateway', 'paystack')
            ->where('external_payment_id', $reference)
            ->where('status', 'pending')
            ->first();
        $cached = Cache::get(PaystackService::CACHE_KEY_SUB_PREFIX.$reference);
        $expected = (float) (
            $pendingPayment?->amount
            ?? (is_array($cached) ? ($cached['expected_amount'] ?? null) : null)
            ?? $plan->price_amount
        );

        if (! $this->amountsMatch($amount, $expected)) {
            Log::warning('Paystack subscription amount mismatch', [
                'reference' => $reference,
                'paid' => $amount,
                'expected' => $expected,
            ]);

            return ['success' => false, 'message' => 'Payment amount does not match expected checkout amount.'];
        }

        $currency = strtoupper((string) ($data['currency'] ?? $this->paystack->getCurrency()));
        $ledgerEventId = $eventId ?: (string) ($data['id'] ?? $reference);

        try {
            $subscription = DB::transaction(function () use ($company, $plan, $amount, $reference, $currency, $ledgerEventId, $data) {
                // Cancel all currently entitlement-granting rows (trial + active).
                Subscription::where('company_id', $company->id)
                    ->whereIn('status', ['active', 'trial'])
                    ->update(['status' => 'cancelled']);

                $subscription = Subscription::create([
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

                $this->billingLedger->record(
                    'paystack',
                    $ledgerEventId,
                    $amount,
                    $company->id,
                    $subscription->id,
                    $currency,
                    'subscription',
                    $reference,
                    [
                        'plan_slug' => $plan->slug,
                        'channel' => $data['channel'] ?? null,
                        'paid_at' => $data['paid_at'] ?? null,
                    ],
                );

                BillingPayment::where('gateway', 'paystack')
                    ->where('external_payment_id', $reference)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'paid',
                        'subscription_id' => $subscription->id,
                        'paid_at' => now(),
                        'currency' => $currency,
                        'amount' => $amount,
                    ]);

                $this->pricing->completeRedemption($reference, $subscription->id);

                return $subscription;
            });
        } catch (Throwable $e) {
            // Race: another webhook may have created the subscription first.
            $existing = Subscription::where('external_payment_id', $reference)->first();
            if ($existing) {
                $this->forgetPending($reference);

                return ['success' => true, 'subscription' => $existing, 'already_processed' => true];
            }

            Log::error('Paystack subscription activation failed: '.$e->getMessage(), ['reference' => $reference]);

            return ['success' => false, 'message' => 'Could not activate subscription.'];
        }

        $this->forgetPending($reference);

        try {
            if ($company->email) {
                $this->mailService->sendSubscriptionConfirmed(
                    $company->email,
                    $plan->name,
                    $subscription->end_date->format('F j, Y')
                );
            }
            $this->lifecycle->notifySubscriptionConfirmed(
                $company,
                $plan->name,
                $subscription->end_date->format('F j, Y')
            );
        } catch (Throwable $e) {
            Log::warning('Paystack subscription email failed: '.$e->getMessage());
        }

        try {
            app(\App\Services\Agent\AgentCommerceProvisioningService::class)->syncForCompany($company->fresh());
        } catch (Throwable $e) {
            Log::warning('Agent commerce provision failed: '.$e->getMessage());
        }

        return ['success' => true, 'subscription' => $subscription];
    }

    /**
     * Verify with Paystack API and activate (callback fallback when webhook is delayed).
     *
     * @return array{success: bool, subscription?: Subscription, message?: string, already_processed?: bool}
     */
    public function verifyAndActivate(string $reference, Company $company): array
    {
        $pending = $this->resolvePending($reference);
        if ($pending && (int) ($pending['company_id'] ?? 0) !== (int) $company->id) {
            return ['success' => false, 'message' => 'This payment does not belong to your company.'];
        }

        $existing = Subscription::where('external_payment_id', $reference)->first();
        if ($existing) {
            if ((int) $existing->company_id !== (int) $company->id) {
                return ['success' => false, 'message' => 'This payment does not belong to your company.'];
            }

            return ['success' => true, 'subscription' => $existing, 'already_processed' => true];
        }

        $verified = $this->paystack->verifyTransaction($reference);
        if (! ($verified['success'] ?? false)) {
            return ['success' => false, 'message' => $verified['error'] ?? 'Payment verification failed.'];
        }

        /** @var array<string, mixed> $data */
        $data = $verified['data'] ?? [];

        return $this->activateFromCharge($data, (string) ($data['id'] ?? $reference));
    }

    public function cancelLocalSubscription(Company $company): array
    {
        $subscription = Subscription::where('company_id', $company->id)
            ->whereIn('status', ['active', 'trial'])
            ->orderByDesc('end_date')
            ->first();

        if (! $subscription) {
            return ['success' => false, 'message' => 'No active subscription to cancel.'];
        }

        if ($subscription->payment_method === 'stripe' || $subscription->stripe_subscription_id) {
            return [
                'success' => false,
                'message' => 'This subscription is managed in Stripe. Use Manage billing to cancel.',
            ];
        }

        $subscription->status = 'cancelled';
        $subscription->save();

        return [
            'success' => true,
            'message' => 'Subscription cancelled. Access remains until '.$subscription->end_date->format('Y-m-d').' unless you renew.',
            'subscription' => $subscription,
        ];
    }

    public function isAllowedCallbackUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $allowed = [];
        foreach ([config('app.url'), config('app.frontend_url'), env('FRONTEND_URL')] as $base) {
            if (! is_string($base) || $base === '') {
                continue;
            }
            $allowedHost = parse_url($base, PHP_URL_HOST);
            if (is_string($allowedHost) && $allowedHost !== '') {
                $allowed[] = strtolower($allowedHost);
            }
        }

        // Local/dev convenience
        $allowed = array_unique(array_merge($allowed, ['localhost', '127.0.0.1']));

        return in_array(strtolower($host), $allowed, true);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{company_id: int, plan_slug: string}|null
     */
    public function resolvePending(string $reference, array $metadata = []): ?array
    {
        $cached = Cache::get(PaystackService::CACHE_KEY_SUB_PREFIX.$reference);
        if (is_array($cached) && ! empty($cached['company_id']) && ! empty($cached['plan_slug'])) {
            return [
                'company_id' => (int) $cached['company_id'],
                'plan_slug' => (string) $cached['plan_slug'],
            ];
        }

        $pendingPayment = BillingPayment::where('gateway', 'paystack')
            ->where('external_payment_id', $reference)
            ->where('status', 'pending')
            ->first();

        if ($pendingPayment) {
            $meta = is_array($pendingPayment->metadata) ? $pendingPayment->metadata : [];
            $companyId = (int) ($meta['company_id'] ?? $pendingPayment->company_id ?? 0);
            $planSlug = (string) ($meta['plan_slug'] ?? '');
            if ($companyId && $planSlug !== '') {
                return ['company_id' => $companyId, 'plan_slug' => $planSlug];
            }
        }

        // Metadata fallback from Paystack payload (still require durable or cache match for safety).
        $companyId = (int) ($metadata['company_id'] ?? 0);
        $planSlug = (string) ($metadata['plan_slug'] ?? '');
        if ($companyId && $planSlug !== '' && ($metadata['type'] ?? '') === 'subscription') {
            return ['company_id' => $companyId, 'plan_slug' => $planSlug];
        }

        return null;
    }

    protected function forgetPending(string $reference): void
    {
        Cache::forget(PaystackService::CACHE_KEY_SUB_PREFIX.$reference);
    }

    public function amountsMatch(float $paid, float $expected): bool
    {
        if ($expected <= 0) {
            return $paid > 0;
        }

        return abs($paid - $expected) <= 0.01;
    }
}
