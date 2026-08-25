<?php

namespace App\Services\PlatformPayments;

use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\MailService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Platform subscription bank-transfer / invoice payments with proof review.
 */
class ManualSubscriptionPaymentService
{
    public function __construct(
        protected MailService $mailService,
    ) {}

    /**
     * @param  array<string, mixed>  $initiationResult  from PlatformManualDriver::initiatePlanPayment
     */
    public function persistPending(Company $company, Plan $plan, array $initiationResult): BillingPayment
    {
        $reference = (string) ($initiationResult['invoice_reference'] ?? ('INV-'.$company->id.'-'.uniqid()));

        return BillingPayment::updateOrCreate(
            [
                'gateway' => 'manual',
                'external_event_id' => $reference,
            ],
            [
                'company_id' => $company->id,
                'subscription_id' => null,
                'external_payment_id' => $reference,
                'amount' => (float) ($initiationResult['amount'] ?? 0),
                'currency' => strtoupper((string) ($initiationResult['currency'] ?? 'KES')),
                'status' => 'pending',
                'payment_type' => 'subscription',
                'metadata' => [
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'plan_slug' => $plan->slug,
                    'instructions' => $initiationResult['instructions'] ?? null,
                    'bank_name' => $initiationResult['bank_name'] ?? null,
                    'account_name' => $initiationResult['account_name'] ?? null,
                    'account_number' => $initiationResult['account_number'] ?? null,
                ],
                'paid_at' => null,
            ]
        );
    }

    public function submitProof(
        Company $company,
        string $reference,
        UploadedFile $proof,
        ?string $note = null,
    ): array {
        $payment = BillingPayment::where('gateway', 'manual')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($reference) {
                $q->where('external_event_id', $reference)
                    ->orWhere('external_payment_id', $reference);
            })
            ->whereIn('status', ['pending', 'awaiting_review'])
            ->first();

        if (! $payment) {
            return ['success' => false, 'message' => 'Pending bank transfer not found.'];
        }

        $dir = 'payment-proofs/'.$company->id;
        $path = $proof->store($dir, 'local');
        if (! $path) {
            return ['success' => false, 'message' => 'Could not store payment proof.'];
        }

        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        if (! empty($meta['proof_path']) && Storage::disk('local')->exists($meta['proof_path'])) {
            Storage::disk('local')->delete($meta['proof_path']);
        }

        $meta['proof_path'] = $path;
        $meta['proof_original_name'] = $proof->getClientOriginalName();
        $meta['proof_note'] = $note;
        $meta['proof_submitted_at'] = now()->toIso8601String();

        $payment->metadata = $meta;
        $payment->status = 'awaiting_review';
        $payment->save();

        return [
            'success' => true,
            'message' => 'Proof submitted. An admin will review and activate your subscription.',
            'payment' => $this->serialize($payment),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForCompany(Company $company): array
    {
        return BillingPayment::where('gateway', 'manual')
            ->where('company_id', $company->id)
            ->where('payment_type', 'subscription')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (BillingPayment $p) => $this->serialize($p))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPendingForAdmin(): array
    {
        return BillingPayment::with('company')
            ->where('gateway', 'manual')
            ->where('payment_type', 'subscription')
            ->whereIn('status', ['pending', 'awaiting_review'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (BillingPayment $p) => $this->serialize($p, true))
            ->values()
            ->all();
    }

    public function approve(BillingPayment $payment, ?int $adminUserId = null): array
    {
        if ($payment->gateway !== 'manual') {
            return ['success' => false, 'message' => 'Not a manual payment.'];
        }
        if (! in_array($payment->status, ['pending', 'awaiting_review'], true)) {
            return ['success' => false, 'message' => 'Payment is not awaiting review.'];
        }

        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $company = Company::find($payment->company_id);
        $planSlug = (string) ($meta['plan_slug'] ?? '');
        $plan = Plan::where('slug', $planSlug)->orWhere('id', $meta['plan_id'] ?? 0)->first();

        if (! $company || ! $plan) {
            return ['success' => false, 'message' => 'Company or plan not found.'];
        }

        $reference = (string) ($payment->external_payment_id ?: $payment->external_event_id);

        try {
            $subscription = DB::transaction(function () use ($company, $plan, $payment, $reference, $adminUserId, $meta) {
                Subscription::where('company_id', $company->id)
                    ->whereIn('status', ['active', 'trial'])
                    ->update(['status' => 'cancelled']);

                $subscription = Subscription::create([
                    'company_id' => $company->id,
                    'plan' => $plan->slug,
                    'status' => 'active',
                    'start_date' => now()->format('Y-m-d'),
                    'end_date' => now()->addMonth()->format('Y-m-d'),
                    'amount' => (float) $payment->amount,
                    'billing_cycle' => 'monthly',
                    'payment_method' => 'manual',
                    'external_payment_id' => $reference,
                ]);

                $payment->status = 'paid';
                $payment->subscription_id = $subscription->id;
                $payment->paid_at = now();
                $payment->metadata = array_merge($meta, [
                    'approved_by' => $adminUserId,
                    'approved_at' => now()->toIso8601String(),
                ]);
                $payment->save();

                return $subscription;
            });
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Could not activate subscription: '.$e->getMessage()];
        }

        try {
            $ownerEmail = $company->email ?: $company->users()->orderBy('id')->value('email');
            if ($ownerEmail) {
                $this->mailService->sendSubscriptionConfirmed(
                    (string) $ownerEmail,
                    $plan->name,
                    $subscription->end_date->format('Y-m-d'),
                );
            }
        } catch (Throwable) {
            // non-fatal
        }

        return [
            'success' => true,
            'message' => 'Payment approved. Subscription is now active.',
            'subscription' => $subscription,
            'payment' => $this->serialize($payment->fresh(), true),
        ];
    }

    public function reject(BillingPayment $payment, ?string $reason = null, ?int $adminUserId = null): array
    {
        if ($payment->gateway !== 'manual') {
            return ['success' => false, 'message' => 'Not a manual payment.'];
        }
        if (! in_array($payment->status, ['pending', 'awaiting_review'], true)) {
            return ['success' => false, 'message' => 'Payment is not awaiting review.'];
        }

        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $meta['rejected_by'] = $adminUserId;
        $meta['rejected_at'] = now()->toIso8601String();
        $meta['rejection_reason'] = $reason;

        $payment->status = 'rejected';
        $payment->metadata = $meta;
        $payment->save();

        return [
            'success' => true,
            'message' => 'Payment rejected.',
            'payment' => $this->serialize($payment, true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(BillingPayment $payment, bool $includeAdmin = false): array
    {
        $meta = is_array($payment->metadata) ? $payment->metadata : [];

        $payload = [
            'id' => (string) $payment->id,
            'reference' => (string) ($payment->external_payment_id ?: $payment->external_event_id),
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'currency' => strtoupper((string) $payment->currency),
            'planSlug' => $meta['plan_slug'] ?? null,
            'instructions' => $meta['instructions'] ?? null,
            'bankName' => $meta['bank_name'] ?? null,
            'accountName' => $meta['account_name'] ?? null,
            'accountNumber' => $meta['account_number'] ?? null,
            'hasProof' => ! empty($meta['proof_path']),
            'proofNote' => $meta['proof_note'] ?? null,
            'proofSubmittedAt' => $meta['proof_submitted_at'] ?? null,
            'createdAt' => $payment->created_at?->toIso8601String(),
        ];

        if ($includeAdmin) {
            $payload['companyId'] = $payment->company_id ? (string) $payment->company_id : null;
            $payload['companyName'] = $payment->company?->name;
            $payload['proofOriginalName'] = $meta['proof_original_name'] ?? null;
            $payload['rejectionReason'] = $meta['rejection_reason'] ?? null;
        }

        return $payload;
    }

    public function proofAbsolutePath(BillingPayment $payment): ?string
    {
        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $path = $meta['proof_path'] ?? null;
        if (! is_string($path) || $path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->path($path);
    }
}
