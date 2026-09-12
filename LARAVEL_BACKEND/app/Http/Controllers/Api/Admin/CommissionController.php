<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionInvoice;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderCommission;
use App\Models\PlatformSetting;
use App\Services\Billing\BillingResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin cockpit for commission-on-sales billing:
 * money owed right now, per-company terms, invoices, and platform defaults.
 */
class CommissionController extends Controller
{
    public function __construct(
        protected BillingResolutionService $billing,
    ) {}

    /**
     * GET /api/admin/commissions/overview
     */
    public function overview(): JsonResponse
    {
        $platform = PlatformSetting::first();
        $companyIds = $this->commissionCompanyIds($platform)->all();

        $owedNow = (float) Company::whereIn('id', $companyIds)->sum('commission_balance_due');
        $settledTotal = (float) OrderCommission::whereIn('company_id', $companyIds)
            ->where('settlement_status', 'settled')
            ->sum('commission_amount');
        $accruedTotal = (float) OrderCommission::whereIn('company_id', $companyIds)
            ->where('settlement_status', 'accrued')
            ->sum('commission_amount');
        $monthStart = now()->startOfMonth();
        $monthCommission = (float) OrderCommission::whereIn('company_id', $companyIds)
            ->where('created_at', '>=', $monthStart)
            ->sum('commission_amount');
        $monthSales = (float) Order::whereIn('company_id', $companyIds)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $monthStart)
            ->sum('total');
        $pendingInvoices = CommissionInvoice::where('status', 'open')->count();
        $pendingInvoicesTotal = (float) CommissionInvoice::where('status', 'open')->sum('amount_due');

        $topDebtors = Company::whereIn('id', $companyIds)
            ->where('commission_balance_due', '>', 0)
            ->orderByDesc('commission_balance_due')
            ->limit(5)
            ->get(['id', 'name', 'commission_balance_due', 'commission_rate', 'billing_model'])
            ->map(fn (Company $c) => [
                'id' => (string) $c->id,
                'name' => $c->name ?? 'Company #'.$c->id,
                'balanceDue' => (float) ($c->commission_balance_due ?? 0),
                'rate' => (float) ($c->commission_rate ?? $platform?->default_commission_rate ?? 5),
            ])
            ->values()
            ->all();

        return response()->json([
            'enabled' => in_array(strtolower((string) ($platform?->default_billing_model ?? 'subscription')), ['commission', 'hybrid'], true),
            'owedNow' => $owedNow,
            'settledTotal' => $settledTotal,
            'accruedTotal' => $accruedTotal,
            'monthCommission' => $monthCommission,
            'monthSales' => $monthSales,
            'companyCount' => count($companyIds),
            'pendingInvoices' => $pendingInvoices,
            'pendingInvoicesTotal' => $pendingInvoicesTotal,
            'topDebtors' => $topDebtors,
            'defaults' => [
                'billingModel' => strtolower((string) ($platform?->default_billing_model ?? 'subscription')),
                'rate' => (float) ($platform?->default_commission_rate ?? 5),
                'threshold' => (float) ($platform?->default_commission_threshold ?? 50),
                'graceDays' => (int) ($platform?->commission_grace_period_days ?? 7),
                'publicSignup' => (bool) ($platform?->allow_public_commission_signup ?? false),
            ],
        ]);
    }

    /**
     * GET /api/admin/commissions/companies
     */
    public function companies(): JsonResponse
    {
        $platform = PlatformSetting::first();
        $monthStart = now()->startOfMonth();

        $rows = Company::whereIn('id', $this->commissionCompanyIds($platform)->all())
            ->orderByDesc('commission_balance_due')
            ->limit(200)
            ->get()
            ->map(function (Company $c) use ($platform, $monthStart) {
                $resolved = $this->billing->resolve($c);
                $monthSales = (float) Order::where('company_id', $c->id)
                    ->where('payment_status', 'paid')
                    ->where('created_at', '>=', $monthStart)
                    ->sum('total');
                $monthOrders = Order::where('company_id', $c->id)
                    ->where('payment_status', 'paid')
                    ->where('created_at', '>=', $monthStart)
                    ->count();
                $balance = (float) ($c->commission_balance_due ?? 0);
                $threshold = (float) $resolved['threshold'];

                return [
                    'id' => (string) $c->id,
                    'name' => $c->name ?? 'Company #'.$c->id,
                    'email' => $c->email,
                    'model' => $resolved['model'],
                    'modelOverridden' => ! empty($c->billing_model),
                    'rate' => (float) $resolved['rate'],
                    'rateOverridden' => $c->commission_rate !== null,
                    'basis' => $resolved['basis'],
                    'balanceDue' => $balance,
                    'threshold' => $threshold,
                    'monthSales' => $monthSales,
                    'monthOrders' => $monthOrders,
                    'overdue' => $threshold > 0 && $balance >= $threshold,
                ];
            })
            ->values()
            ->all();

        return response()->json(['companies' => $rows]);
    }

    /**
     * GET /api/admin/commissions/invoices
     */
    public function invoices(): JsonResponse
    {
        $rows = CommissionInvoice::with('company:id,name')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (CommissionInvoice $inv) => [
                'id' => (string) $inv->id,
                'number' => $inv->invoice_number,
                'companyId' => (string) $inv->company_id,
                'companyName' => $inv->company?->name ?? 'Company #'.$inv->company_id,
                'periodStart' => $inv->period_start?->format('Y-m-d'),
                'periodEnd' => $inv->period_end?->format('Y-m-d'),
                'ordersCount' => (int) $inv->orders_count,
                'grossSales' => (float) $inv->gross_sales,
                'amountDue' => (float) $inv->amount_due,
                'status' => $inv->status,
                'dueDate' => $inv->due_date?->format('Y-m-d'),
                'paidAt' => $inv->paid_at?->format('Y-m-d'),
                'paymentReference' => $inv->payment_reference,
                'paymentMethod' => $inv->payment_method,
            ])
            ->values()
            ->all();

        return response()->json(['invoices' => $rows]);
    }

    /**
     * POST /api/admin/commissions/invoices/generate
     *
     * One open invoice per commission company that owes money and has no open
     * invoice yet. Links all unlinked accrued order commissions.
     */
    public function generate(): JsonResponse
    {
        $platform = PlatformSetting::first();
        $graceDays = max(1, (int) ($platform?->commission_grace_period_days ?? 7));
        $created = 0;

        $companies = Company::whereIn('id', $this->commissionCompanyIds($platform)->all())
            ->where('commission_balance_due', '>', 0)
            ->get();

        foreach ($companies as $company) {
            $hasOpen = CommissionInvoice::where('company_id', $company->id)
                ->where('status', 'open')
                ->exists();
            if ($hasOpen) {
                continue;
            }

            $pending = OrderCommission::where('company_id', $company->id)
                ->where('settlement_status', 'accrued')
                ->whereNull('commission_invoice_id')
                ->get();

            $number = 'COM-'.now()->format('Ym').'-'.$company->id;
            $suffix = 0;
            while (CommissionInvoice::where('invoice_number', $number.($suffix > 0 ? '-'.$suffix : ''))->exists()) {
                $suffix++;
            }
            $number .= $suffix > 0 ? '-'.$suffix : '';

            $invoice = DB::transaction(function () use ($company, $pending, $number, $graceDays) {
                $inv = CommissionInvoice::create([
                    'company_id' => $company->id,
                    'invoice_number' => $number,
                    'period_start' => now()->startOfMonth()->toDateString(),
                    'period_end' => now()->toDateString(),
                    'orders_count' => $pending->count(),
                    'gross_sales' => round($pending->sum('order_total'), 2),
                    'amount_due' => round((float) $company->commission_balance_due, 2),
                    'status' => 'open',
                    'due_date' => now()->addDays($graceDays),
                ]);
                OrderCommission::whereIn('id', $pending->pluck('id')->all())
                    ->update(['commission_invoice_id' => $inv->id]);

                return $inv;
            });

            if ($invoice) {
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'message' => $created > 0
                ? "Generated {$created} invoice(s)."
                : 'Nothing to invoice — no company owes money without an open invoice.',
        ]);
    }

    /**
     * POST /api/admin/commissions/invoices/{invoice}/mark-paid
     */
    public function markPaid(Request $request, CommissionInvoice $invoice): JsonResponse
    {
        if ($invoice->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'Only open invoices can be marked paid.'], 422);
        }

        $validated = $request->validate([
            'payment_reference' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:120',
        ]);

        DB::transaction(function () use ($invoice, $validated) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_reference' => $validated['payment_reference'] ?? null,
                'payment_method' => $validated['payment_method'] ?? null,
            ]);

            $company = $invoice->company;
            if ($company) {
                $company->decrement('commission_balance_due', min(
                    (float) ($company->commission_balance_due ?? 0),
                    (float) $invoice->amount_due
                ));
            }

            OrderCommission::where('commission_invoice_id', $invoice->id)
                ->where('settlement_status', 'accrued')
                ->update(['settlement_status' => 'settled', 'settled_at' => now()]);
        });

        return response()->json(['success' => true, 'message' => "Invoice {$invoice->invoice_number} marked paid."]);
    }

    /**
     * Companies on commission billing: explicit company override, or platform
     * default when the company has no override. Plus anyone carrying a balance.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    protected function commissionCompanyIds(?PlatformSetting $platform)
    {
        $platformDefault = strtolower((string) ($platform?->default_billing_model ?? 'subscription'));
        $defaultIsCommission = in_array($platformDefault, ['commission', 'hybrid'], true);

        $query = Company::query()->where(function ($q) use ($defaultIsCommission) {
            $q->whereIn('billing_model', ['commission', 'hybrid']);
            if ($defaultIsCommission) {
                $q->orWhereNull('billing_model');
            }
            $q->orWhere('commission_balance_due', '>', 0);
        });

        return $query->pluck('id');
    }
}
