<?php

namespace App\Console\Commands;

use App\Models\CommissionInvoice;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderCommission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * TEMPORARY one-off remediation command.
 *
 * Removes a fixed set of test orders placed on the Jostina bookshop and reverses
 * the commissions they accrued (order_commissions rows + commission_balance_due).
 *
 * Safe to run repeatedly: it only ever touches the explicit order-number
 * allowlist for the target company, and stops after the first successful run
 * via a marker file. Delete this command (and its schedule entry) after it runs.
 */
class RevertTestOrderCommissionsCommand extends Command
{
    protected $signature = 'orders:revert-test-commissions
        {--dry-run : Report what would be removed without changing anything}
        {--company=18 : Target company id}
        {--orders= : Comma-separated order numbers (defaults to the recorded test set)}';

    protected $description = 'One-off: remove specific test orders on the Jostina bookshop and reverse their commissions.';

    /**
     * The exact test orders (Sep 17-18) identified from production commission logs.
     */
    private const DEFAULT_ORDER_NUMBERS = [
        'ORD-LX1XXJSD',
        'ORD-9AGTXLDH',
        'ORD-BSCGB03U',
        'ORD-FH2TWS80',
        'ORD-USUZ7SII',
        'ORD-5XYP7ZLA',
        'ORD-LKCVQWKG',
        'ORD-BECTYJGF',
    ];

    private const MARKER = 'revert-test-orders.done';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && Storage::disk('local')->exists(self::MARKER)) {
            $this->info('Already completed previously; nothing to do.');

            return self::SUCCESS;
        }

        $companyId = (int) $this->option('company');
        $numbers = array_values(array_filter(array_map('trim', explode(',', (string) (
            $this->option('orders') ?: implode(',', self::DEFAULT_ORDER_NUMBERS)
        )))));

        $company = Company::find($companyId);
        if (! $company) {
            $this->error("Company {$companyId} not found.");

            return self::FAILURE;
        }

        $orders = Order::where('company_id', $companyId)
            ->whereIn('order_number', $numbers)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No matching test orders found (already removed).');

            return self::SUCCESS;
        }

        // Safety: only act on orders that actually carry a commission row.
        $commissions = OrderCommission::whereIn('order_id', $orders->pluck('id'))
            ->get()
            ->keyBy('order_id');

        $eligible = $orders->filter(fn (Order $o) => $commissions->has($o->id))->values();

        if ($eligible->isEmpty()) {
            $this->info('Matching orders carry no commission; nothing to do.');

            return self::SUCCESS;
        }

        $orderIds = $eligible->pluck('id')->all();
        $eligibleCommissions = $commissions->only($orderIds);

        $commissionTotal = round((float) $eligibleCommissions->sum('commission_amount'), 2);

        // Only manual_direct commissions that have not already been settled
        // incremented the company's balance, so only those reduce it.
        $balanceReduction = round((float) $eligibleCommissions
            ->filter(fn (OrderCommission $c) => $c->collection_mode === 'manual_direct'
                && $c->settlement_status !== 'settled')
            ->sum('commission_amount'), 2);

        // ── Backup every affected row to a JSON file under storage/app ──────────
        $backup = [
            'generated_at'            => now()->toIso8601String(),
            'company_id'              => $companyId,
            'company_name'            => $company->name,
            'orders'                  => Order::whereIn('id', $orderIds)->get()->toArray(),
            'order_products'          => DB::table('order_products')->whereIn('order_id', $orderIds)->get()->toArray(),
            'order_commissions'       => OrderCommission::whereIn('order_id', $orderIds)->get()->toArray(),
            'payment_recovery_attempts' => DB::getSchemaBuilder()->hasTable('payment_recovery_attempts')
                ? DB::table('payment_recovery_attempts')->whereIn('order_id', $orderIds)->get()->toArray()
                : [],
        ];
        $backupPath = 'revert-test-orders-'.now()->format('Ymd-His').'.json';

        if (! $dryRun) {
            Storage::disk('local')->put($backupPath, json_encode(
                $backup,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ));
        }

        $this->line("Company: {$company->name} (#{$companyId})");
        $this->line('Orders ('.$eligible->count().'): '.implode(', ', $eligible->pluck('order_number')->all()));
        $this->line("Commission rows: {$eligible->count()} | total KES {$commissionTotal} | balance reduction KES {$balanceReduction}");
        $this->line("Backup: storage/app/private/{$backupPath}");

        if ($dryRun) {
            $this->warn('Dry run - no changes made.');

            return self::SUCCESS;
        }

        // ── Refuse to touch anything already on a PAID invoice ─────────────────
        $invoiceIds = $eligibleCommissions->pluck('commission_invoice_id')->filter()->unique()->values()->all();
        $openInvoices = CommissionInvoice::whereIn('id', $invoiceIds)->get();

        foreach ($openInvoices as $invoice) {
            if ($invoice->status === 'paid') {
                $this->error("Commission invoice {$invoice->invoice_number} is already paid - aborting for manual review.");

                return self::FAILURE;
            }
        }

        DB::transaction(function () use (
            $orderIds,
            $eligibleCommissions,
            $balanceReduction,
            $company,
            $openInvoices
        ): void {
            // Reduce any OPEN invoices that include these commissions.
            foreach ($openInvoices as $invoice) {
                $affected = $eligibleCommissions->where('commission_invoice_id', $invoice->id);
                if ($affected->isEmpty()) {
                    continue;
                }

                $newCount = max(0, (int) $invoice->orders_count - $affected->count());
                if ($newCount === 0) {
                    $invoice->delete();
                    continue;
                }

                $invoice->update([
                    'orders_count' => $newCount,
                    'gross_sales'  => max(0, round((float) $invoice->gross_sales - (float) $affected->sum('order_total'), 2)),
                    'amount_due'   => max(0, round((float) $invoice->amount_due - (float) $affected->sum('commission_amount'), 2)),
                ]);
            }

            // Detach stale order references that have no FK cascade.
            if (DB::getSchemaBuilder()->hasTable('storefront_events')) {
                DB::table('storefront_events')->whereIn('order_id', $orderIds)->update(['order_id' => null]);
            }

            // Deleting the orders cascades order_products, order_commissions and
            // payment_recovery_attempts, and nulls out FK references elsewhere.
            Order::whereIn('id', $orderIds)->delete();

            // Reduce the accrued commission balance (clamped at zero).
            if ($balanceReduction > 0) {
                $current = (float) $company->fresh()->commission_balance_due;
                $company->decrement('commission_balance_due', min($current, $balanceReduction));
            }
        });

        Storage::disk('local')->put(self::MARKER, now()->toIso8601String());

        Log::info('[orders:revert-test-commissions] Removed test orders and reversed commissions', [
            'company_id'        => $companyId,
            'orders'            => $eligible->pluck('order_number')->all(),
            'commission_total'  => $commissionTotal,
            'balance_reduction' => $balanceReduction,
            'backup'            => $backupPath,
        ]);

        $this->info("Removed {$eligible->count()} test order(s); reversed KES {$commissionTotal} commission (balance -{$balanceReduction}).");

        return self::SUCCESS;
    }
}
