<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Agent\AgentCommerceProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::with('company');

        if ($request->filled('plan') && $request->plan !== 'all') {
            $query->where('plan', $request->plan);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $subscriptions = $query->orderByDesc('created_at')->get();
        $data = $subscriptions->map(fn (Subscription $s) => [
            'id' => (string) $s->id,
            'companyId' => (string) $s->company_id,
            'companyName' => $s->company?->name ?? '',
            'plan' => $s->plan,
            'status' => $s->status,
            'startDate' => $s->start_date->format('Y-m-d'),
            'endDate' => $s->end_date->format('Y-m-d'),
            'amount' => (float) $s->amount,
            'billingCycle' => $s->billing_cycle,
        ]);

        return response()->json($data->values()->all());
    }

    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'sometimes|string|max:50',
            'status' => 'sometimes|string|in:active,trial,cancelled,expired,pending',
            'startDate' => 'sometimes|date',
            'endDate' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0',
            'billingCycle' => 'sometimes|string|in:monthly,yearly',
        ]);

        if (isset($validated['startDate'])) {
            $subscription->start_date = $validated['startDate'];
        }
        if (isset($validated['endDate'])) {
            $subscription->end_date = $validated['endDate'];
        }
        if (isset($validated['plan'])) {
            $subscription->plan = $validated['plan'];
        }
        if (isset($validated['status'])) {
            $subscription->status = $validated['status'];
        }
        if (isset($validated['billingCycle'])) {
            $subscription->billing_cycle = $validated['billingCycle'];
        }

        $planChanged = array_key_exists('plan', $validated);
        $statusChanged = array_key_exists('status', $validated);
        $billingChanged = array_key_exists('billingCycle', $validated);
        $manualAssign = $planChanged || $statusChanged || $billingChanged;

        if (array_key_exists('amount', $validated)) {
            $subscription->amount = $validated['amount'];
        } elseif ($manualAssign) {
            $subscription->amount = $this->resolveAmountForSubscription($subscription);
        }

        // Manual admin assign replaces Stripe-managed billing for this record.
        if ($manualAssign) {
            $subscription->stripe_subscription_id = null;
        }

        // Converting trial → active without an end date refresh: keep existing end_date
        // (admin can edit dates separately). Ensure active/trial rows are not past-due.
        if (($subscription->status === 'active' || $subscription->status === 'trial')
            && $subscription->end_date
            && $subscription->end_date->lt(now()->startOfDay())
        ) {
            $days = $subscription->billing_cycle === 'yearly' ? 365 : 30;
            $subscription->end_date = now()->addDays($days)->toDateString();
        }

        $subscription->save();

        if ($subscription->company) {
            app(AgentCommerceProvisioningService::class)->syncForCompany($subscription->company->fresh());
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription updated.',
            'subscription' => [
                'id' => (string) $subscription->id,
                'companyId' => (string) $subscription->company_id,
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'startDate' => $subscription->start_date->format('Y-m-d'),
                'endDate' => $subscription->end_date->format('Y-m-d'),
                'amount' => (float) $subscription->amount,
                'billingCycle' => $subscription->billing_cycle,
            ],
        ]);
    }

    protected function resolveAmountForSubscription(Subscription $subscription): float
    {
        if ($subscription->status === 'trial') {
            return 0.0;
        }

        $plan = Plan::query()->where('slug', $subscription->plan)->first();
        if (! $plan) {
            return (float) $subscription->amount;
        }

        if ($plan->is_free) {
            return 0.0;
        }

        $monthly = (float) ($plan->price_amount ?? 0);
        if ($subscription->billing_cycle === 'yearly') {
            return round($monthly * 12, 2);
        }

        return $monthly;
    }
}
