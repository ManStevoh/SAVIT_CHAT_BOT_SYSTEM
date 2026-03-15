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
}
