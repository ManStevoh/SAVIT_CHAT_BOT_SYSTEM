<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
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
        if (!$companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }
        // Return billing invoices: extend with Payment/Invoice model when available
        $invoices = [];
        return response()->json($invoices);
    }
}
