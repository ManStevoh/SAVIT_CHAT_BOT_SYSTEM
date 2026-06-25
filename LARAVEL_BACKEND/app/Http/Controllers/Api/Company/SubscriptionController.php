<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Growth\GrowthLimitService;
use App\Services\PlanLimitService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected StripeService $stripe
    ) {}

    public function show(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (!$companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $subscription = Subscription::where('company_id', $companyId)->orderByDesc('end_date')->first();
        $company = $request->user()->company;

        if (!$subscription) {
            return response()->json([
                'id' => '0',
                'companyId' => (string) $companyId,
                'companyName' => $company?->name ?? '',
                'plan' => 'starter',
                'status' => 'trial',
                'startDate' => now()->format('Y-m-d'),
                'endDate' => now()->addDays(14)->format('Y-m-d'),
                'amount' => 0,
                'billingCycle' => 'monthly',
            ]);
        }

        return response()->json([
            'id' => (string) $subscription->id,
            'companyId' => (string) $subscription->company_id,
            'companyName' => $subscription->company?->name ?? '',
            'plan' => $subscription->plan,
            'status' => $subscription->status,
            'startDate' => $subscription->start_date->format('Y-m-d'),
            'endDate' => $subscription->end_date->format('Y-m-d'),
            'amount' => (float) $subscription->amount,
            'billingCycle' => $subscription->billing_cycle,
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $company = $request->user()->company;
        if (! $company?->stripe_customer_id || ! StripeService::isEnabled()) {
            return response()->json([]);
        }

        $invoices = $this->stripe->listInvoicesForCustomer($company->stripe_customer_id);

        return response()->json($invoices);
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
}
