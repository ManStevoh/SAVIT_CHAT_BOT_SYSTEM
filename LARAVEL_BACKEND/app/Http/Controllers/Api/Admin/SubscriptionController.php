<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
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
            'status' => 'sometimes|string|in:active,cancelled,expired,pending',
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
        if (isset($validated['amount'])) {
            $subscription->amount = $validated['amount'];
        }
        if (isset($validated['billingCycle'])) {
            $subscription->billing_cycle = $validated['billingCycle'];
        }
        $subscription->save();

        return response()->json([
            'success' => true,
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
}
