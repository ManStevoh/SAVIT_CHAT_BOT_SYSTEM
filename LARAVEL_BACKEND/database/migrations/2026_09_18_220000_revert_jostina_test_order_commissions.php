<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * One-off data remediation: remove a fixed set of test orders placed on the
 * Jostina bookshop (company 18) and reverse the commissions they accrued.
 *
 * This runs automatically through the deploy pipeline (`php artisan migrate
 * --force`). It is strictly allowlisted by order number + company, requires a
 * matching commission row, is idempotent, and is a harmless no-op on any
 * database that does not contain these orders (CI, local, staging).
 */
return new class extends Migration
{
    private const COMPANY_ID = 18;

    /** The exact test orders (Sep 17-18) identified from production commission logs. */
    private const ORDER_NUMBERS = [
        'ORD-LX1XXJSD',
        'ORD-9AGTXLDH',
        'ORD-BSCGB03U',
        'ORD-FH2TWS80',
        'ORD-USUZ7SII',
        'ORD-5XYP7ZLA',
        'ORD-LKCVQWKG',
        'ORD-BECTYJGF',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('order_commissions')) {
            return;
        }

        $orders = DB::table('orders')
            ->where('company_id', self::COMPANY_ID)
            ->whereIn('order_number', self::ORDER_NUMBERS)
            ->get();

        if ($orders->isEmpty()) {
            return; // Fresh database, or already remediated.
        }

        $orderIds = $orders->pluck('id')->all();

        // Only ever touch orders that actually carry a commission row.
        $commissions = DB::table('order_commissions')
            ->whereIn('order_id', $orderIds)
            ->get();

        $eligibleIds = $commissions->pluck('order_id')->unique()->values()->all();
        if (empty($eligibleIds)) {
            return;
        }

        $eligible = $commissions->whereIn('order_id', $eligibleIds);

        $commissionTotal = round((float) $eligible->sum('commission_amount'), 2);
        $balanceReduction = round((float) $eligible
            ->filter(fn ($c) => ($c->collection_mode ?? null) === 'manual_direct'
                && ($c->settlement_status ?? null) !== 'settled')
            ->sum('commission_amount'), 2);

        // ── Back up every affected row (best-effort; also written to the log) ──
        $backup = [
            'generated_at'              => now()->toIso8601String(),
            'company_id'                => self::COMPANY_ID,
            'company_name'              => DB::table('companies')->where('id', self::COMPANY_ID)->value('name'),
            'orders'                    => $orders->toArray(),
            'order_products'            => DB::table('order_products')->whereIn('order_id', $eligibleIds)->get()->toArray(),
            'order_commissions'         => $commissions->toArray(),
            'payment_recovery_attempts' => Schema::hasTable('payment_recovery_attempts')
                ? DB::table('payment_recovery_attempts')->whereIn('order_id', $eligibleIds)->get()->toArray()
                : [],
        ];

        try {
            Storage::disk('local')->put(
                'revert-test-orders-' . now()->format('Ymd-His') . '.json',
                json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        } catch (\Throwable $e) {
            Log::warning('[revert-test-orders] backup file write failed: ' . $e->getMessage(), ['backup' => $backup]);
        }

        // ── Refuse to touch anything already settled on a PAID invoice ─────────
        $invoiceIds = Schema::hasTable('commission_invoices')
            ? $commissions->pluck('commission_invoice_id')->filter()->unique()->values()->all()
            : [];

        if (! empty($invoiceIds)
            && DB::table('commission_invoices')->whereIn('id', $invoiceIds)->where('status', 'paid')->exists()) {
            Log::error('[revert-test-orders] aborted: a linked commission invoice is already paid.', [
                'invoice_ids' => $invoiceIds,
            ]);

            return;
        }

        DB::transaction(function () use ($eligibleIds, $eligible, $balanceReduction, $invoiceIds) {
            // Reduce any OPEN invoices that include these commissions.
            if (! empty($invoiceIds)) {
                foreach (DB::table('commission_invoices')->whereIn('id', $invoiceIds)->get() as $invoice) {
                    $affected = $eligible->where('commission_invoice_id', $invoice->id);
                    if ($affected->isEmpty()) {
                        continue;
                    }

                    $newCount = max(0, (int) $invoice->orders_count - $affected->count());
                    if ($newCount === 0) {
                        DB::table('commission_invoices')->where('id', $invoice->id)->delete();
                        continue;
                    }

                    DB::table('commission_invoices')->where('id', $invoice->id)->update([
                        'orders_count' => $newCount,
                        'gross_sales'  => max(0, round((float) $invoice->gross_sales - (float) $affected->sum('order_total'), 2)),
                        'amount_due'   => max(0, round((float) $invoice->amount_due - (float) $affected->sum('commission_amount'), 2)),
                    ]);
                }
            }

            // Detach stale order references that have no FK cascade.
            if (Schema::hasTable('storefront_events') && Schema::hasColumn('storefront_events', 'order_id')) {
                DB::table('storefront_events')->whereIn('order_id', $eligibleIds)->update(['order_id' => null]);
            }

            // Deleting the orders cascades order_products, order_commissions and
            // payment_recovery_attempts, and nulls out FK references elsewhere.
            DB::table('orders')->whereIn('id', $eligibleIds)->delete();

            // Reverse the accrued commission balance (clamped at zero).
            if ($balanceReduction > 0 && Schema::hasColumn('companies', 'commission_balance_due')) {
                $current = (float) (DB::table('companies')->where('id', self::COMPANY_ID)->value('commission_balance_due') ?? 0);
                DB::table('companies')->where('id', self::COMPANY_ID)->update([
                    'commission_balance_due' => max(0, round($current - $balanceReduction, 2)),
                ]);
            }
        });

        Log::info('[revert-test-orders] Removed test orders and reversed commissions', [
            'company_id'        => self::COMPANY_ID,
            'orders_removed'    => count($eligibleIds),
            'order_numbers'     => self::ORDER_NUMBERS,
            'commission_total'  => $commissionTotal,
            'balance_reduction' => $balanceReduction,
        ]);
    }

    public function down(): void
    {
        // One-off data remediation; there is nothing to reverse.
    }
};
