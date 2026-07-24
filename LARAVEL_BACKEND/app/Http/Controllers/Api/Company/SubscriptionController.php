<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Growth\GrowthLimitService;
use App\Services\PaystackService;
use App\Services\PaystackSubscriptionService;
use App\Services\PlanLimitService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected StripeService $stripe,
        protected PaystackSubscriptionService $paystackSubscriptions,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $subscription = Subscription::where('company_id', $companyId)->orderByDesc('end_date')->first();
        $company = $request->user()->company;

        if (! $subscription) {
            $starter = Plan::where('slug', 'starter')->first();

            return response()->json([
                'id' => '0',
                'companyId' => (string) $companyId,
                'companyName' => $company?->name ?? '',
                'plan' => 'starter',
                'planName' => $starter?->name ?? 'Starter',
                'status' => 'trial',
                'startDate' => now()->format('Y-m-d'),
                'endDate' => now()->addDays(14)->format('Y-m-d'),
                'amount' => 0,
                'billingCycle' => 'monthly',
                'paymentMethod' => null,
                'currency' => null,
                'daysRemaining' => 14,
                'isExpiringSoon' => false,
                'accessEndsLabel' => 'Trial ends',
            ]);
        }

        $planModel = Plan::where('slug', $subscription->plan)->first();
        $daysRemaining = (int) now()->startOfDay()->diffInDays($subscription->end_date->copy()->startOfDay(), false);
        $status = $subscription->status;
        $accessEndsLabel = match (true) {
            $status === 'cancelled' => 'Access until',
            $status === 'expired' => 'Ended on',
            $status === 'trial' => 'Trial ends',
            default => 'Renews on',
        };

        return response()->json([
            'id' => (string) $subscription->id,
            'companyId' => (string) $subscription->company_id,
            'companyName' => $subscription->company?->name ?? '',
            'plan' => $subscription->plan,
            'planName' => $planModel?->name ?? ucfirst((string) $subscription->plan),
            'status' => $status,
            'startDate' => $subscription->start_date->format('Y-m-d'),
            'endDate' => $subscription->end_date->format('Y-m-d'),
            'amount' => (float) $subscription->amount,
            'billingCycle' => $subscription->billing_cycle,
            'paymentMethod' => $subscription->payment_method,
            'currency' => $this->currencyForSubscription($subscription),
            'daysRemaining' => max(0, $daysRemaining),
            'isExpiringSoon' => in_array($status, ['active', 'trial', 'cancelled'], true) && $daysRemaining >= 0 && $daysRemaining <= 7,
            'accessEndsLabel' => $accessEndsLabel,
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $ledger = BillingPayment::where('company_id', $companyId)
            ->where('payment_type', 'subscription')
            ->where('status', 'paid')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (BillingPayment $p) {
                $currency = strtoupper((string) ($p->currency ?: 'USD'));
                $amount = number_format((float) $p->amount, 2);

                return [
                    'id' => strtoupper($p->gateway).'-'.($p->external_payment_id ?: $p->id),
                    'date' => ($p->paid_at ?? $p->created_at)?->format('Y-m-d') ?? now()->format('Y-m-d'),
                    'amount' => $currency.' '.$amount,
                    'status' => 'paid',
                    'invoicePdf' => null,
                    'gateway' => $p->gateway,
                ];
            })
            ->values()
            ->all();

        if ($ledger !== []) {
            return response()->json($ledger);
        }

        $company = $request->user()->company;
        if (! $company?->stripe_customer_id || ! StripeService::isEnabled()) {
            return response()->json([]);
        }

        $invoices = $this->stripe->listInvoicesForCustomer($company->stripe_customer_id);

        return response()->json($invoices);
    }

    public function cancel(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $result = $this->paystackSubscriptions->cancelLocalSubscription($company);
        if (! ($result['success'] ?? false)) {
            return response()->json(['success' => false, 'message' => $result['message'] ?? 'Cancel failed.'], 422);
        }

        $subscription = $result['subscription'];

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'subscription' => [
                'id' => (string) $subscription->id,
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'endDate' => $subscription->end_date->format('Y-m-d'),
                'paymentMethod' => $subscription->payment_method,
            ],
        ]);
    }

    /**
     * Usage stats for current billing period.
     * GET /api/company/subscription/usage
     */
    public function usage(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $subscription = Subscription::where('company_id', $companyId)->orderByDesc('end_date')->first();
        $plan = $subscription?->plan ?? 'starter';
        $company = $request->user()->company;
        $messageCount = PlanLimitService::getMessagesUsedInCurrentPeriod($company);
        $teamCount = User::where('company_id', $companyId)->count();
        $planLimits = PlanLimitService::getLimitsForPlan($plan);

        $growth = GrowthLimitService::usageSummary($company);
        $aiUsage = app(\App\Services\AI\AiBillingService::class)->usageSummary($company);

        $items = [
            ['name' => 'Messages', 'used' => $messageCount, 'limit' => $planLimits['messages']],
            ['name' => 'Team members', 'used' => $teamCount, 'limit' => $planLimits['team']],
        ];

        if ($aiUsage['platformCostLimitUsd'] !== null) {
            $items[] = [
                'name' => 'Platform AI spend (USD)',
                'used' => (int) round($aiUsage['platformBilledCostUsd'] * 100),
                'limit' => (int) round($aiUsage['platformCostLimitUsd'] * 100),
                'unit' => 'cents',
            ];
        }

        if ($growth['growthEnabled']) {
            $items[] = ['name' => 'AI posts (this month)', 'used' => $growth['aiPostsUsed'], 'limit' => $growth['aiPostsLimit']];
            $items[] = ['name' => 'AI images (this month)', 'used' => $growth['aiImagesUsed'], 'limit' => $growth['aiImagesLimit']];
            $items[] = ['name' => 'Social platforms', 'used' => $growth['platformsConnected'], 'limit' => $growth['platformLimit']];
        }

        $warnings = GrowthLimitService::usageWarnings($company);

        return response()->json([
            'items' => $items,
            'growth' => $growth,
            'aiUsage' => $aiUsage,
            'warnings' => $warnings,
            'upgradeUrl' => rtrim(config('app.frontend_url', config('app.url')), '/').'/dashboard/subscription',
        ]);
    }

    protected function currencyForSubscription(Subscription $subscription): ?string
    {
        if ($subscription->payment_method === 'paystack') {
            return strtoupper(app(PaystackService::class)->getCurrency());
        }

        $payment = BillingPayment::where('subscription_id', $subscription->id)
            ->where('status', 'paid')
            ->orderByDesc('id')
            ->first();

        return $payment?->currency ? strtoupper((string) $payment->currency) : null;
    }
}
