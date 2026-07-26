<?php

namespace App\Services\Orders;

use App\Models\Company;
use App\Models\Product;
use App\Models\TaxRate;
use Illuminate\Support\Collection;

/**
 * Resolve company tax rates and compute order/line money.
 */
class TaxCalculationService
{
    /**
     * @param  list<array{
     *   product_id?: int|null,
     *   name?: string,
     *   price: float|int|string,
     *   quantity: int|float|string,
     *   tax_rate_id?: int|null
     * }>  $items
     * @return array{
     *   subtotal: float,
     *   tax_total: float,
     *   total: float,
     *   tax_breakdown: list<array{name: string, code: ?string, rate: float, inclusive: bool, amount: float}>,
     *   lines: list<array{
     *     tax_rate_id: ?int,
     *     tax_name: ?string,
     *     tax_code: ?string,
     *     tax_rate: ?float,
     *     tax_inclusive: bool,
     *     tax_amount: float,
     *     line_subtotal: float,
     *     line_total: float
     *   }>
     * }
     */
    public function calculateForCompany(Company $company, array $items): array
    {
        $company->loadMissing('settings');
        $taxEnabled = (bool) ($company->settings?->tax_enabled ?? false);

        $productIds = collect($items)
            ->map(fn ($item) => isset($item['product_id']) ? (int) $item['product_id'] : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Product> $products */
        $products = $productIds === []
            ? collect()
            : Product::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

        $defaultRate = $taxEnabled
            ? TaxRate::query()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->where('is_default', true)
                ->first()
            : null;

        $rateCache = [];
        $lines = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $total = 0.0;
        /** @var array<string, array{name: string, code: ?string, rate: float, inclusive: bool, amount: float}> $breakdown */
        $breakdown = [];

        foreach ($items as $item) {
            $unitPrice = round((float) ($item['price'] ?? 0), 2);
            $qty = max(0, (int) ($item['quantity'] ?? 0));
            $catalogLine = round($unitPrice * $qty, 2);

            $rate = null;
            if ($taxEnabled) {
                $explicitId = isset($item['tax_rate_id']) ? (int) $item['tax_rate_id'] : null;
                $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
                $product = $productId ? $products->get($productId) : null;

                $resolvedId = $explicitId
                    ?: ($product?->tax_rate_id ? (int) $product->tax_rate_id : null);

                if ($resolvedId) {
                    if (! array_key_exists($resolvedId, $rateCache)) {
                        $rateCache[$resolvedId] = TaxRate::query()
                            ->where('company_id', $company->id)
                            ->where('id', $resolvedId)
                            ->where('is_active', true)
                            ->first();
                    }
                    $rate = $rateCache[$resolvedId];
                }

                if (! $rate) {
                    $rate = $defaultRate;
                }
            }

            $computed = $this->computeLine($catalogLine, $rate);
            $lines[] = $computed;

            $subtotal += $computed['line_subtotal'];
            $taxTotal += $computed['tax_amount'];
            $total += $computed['line_total'];

            if ($computed['tax_amount'] > 0 && $computed['tax_name']) {
                $key = ($computed['tax_code'] ?: $computed['tax_name']).'|'.$computed['tax_rate'].'|'.($computed['tax_inclusive'] ? '1' : '0');
                if (! isset($breakdown[$key])) {
                    $breakdown[$key] = [
                        'name' => (string) $computed['tax_name'],
                        'code' => $computed['tax_code'],
                        'rate' => (float) $computed['tax_rate'],
                        'inclusive' => (bool) $computed['tax_inclusive'],
                        'amount' => 0.0,
                    ];
                }
                $breakdown[$key]['amount'] = round($breakdown[$key]['amount'] + $computed['tax_amount'], 2);
            }
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($total, 2),
            'tax_breakdown' => array_values($breakdown),
            'lines' => $lines,
        ];
    }

    /**
     * @return array{
     *   tax_rate_id: ?int,
     *   tax_name: ?string,
     *   tax_code: ?string,
     *   tax_rate: ?float,
     *   tax_inclusive: bool,
     *   tax_amount: float,
     *   line_subtotal: float,
     *   line_total: float
     * }
     */
    public function computeLine(float $catalogLineTotal, ?TaxRate $rate): array
    {
        $catalogLineTotal = round(max(0, $catalogLineTotal), 2);

        if (! $rate || (float) $rate->rate <= 0) {
            return [
                'tax_rate_id' => null,
                'tax_name' => null,
                'tax_code' => null,
                'tax_rate' => null,
                'tax_inclusive' => false,
                'tax_amount' => 0.0,
                'line_subtotal' => $catalogLineTotal,
                'line_total' => $catalogLineTotal,
            ];
        }

        $percent = (float) $rate->rate;
        $inclusive = (bool) $rate->is_inclusive;

        if ($inclusive) {
            $lineTotal = $catalogLineTotal;
            $taxAmount = round($lineTotal * ($percent / (100 + $percent)), 2);
            $lineSubtotal = round($lineTotal - $taxAmount, 2);
        } else {
            $lineSubtotal = $catalogLineTotal;
            $taxAmount = round($lineSubtotal * ($percent / 100), 2);
            $lineTotal = round($lineSubtotal + $taxAmount, 2);
        }

        return [
            'tax_rate_id' => (int) $rate->id,
            'tax_name' => (string) $rate->name,
            'tax_code' => $rate->code ? (string) $rate->code : null,
            'tax_rate' => $percent,
            'tax_inclusive' => $inclusive,
            'tax_amount' => $taxAmount,
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal,
        ];
    }

    /**
     * Human-readable money lines for WhatsApp / cart summaries.
     *
     * @param  callable(float): string  $formatMoney
     * @param  array{subtotal: float, tax_total: float, total: float, tax_breakdown: list<array{name: string, code: ?string, rate: float, inclusive: bool, amount: float}>}  $calc
     * @return list<string>
     */
    public function formatSummaryLines(array $calc, callable $formatMoney): array
    {
        $lines = [];
        if (($calc['tax_total'] ?? 0) > 0) {
            $lines[] = 'Subtotal: '.$formatMoney((float) $calc['subtotal']);
            foreach ($calc['tax_breakdown'] ?? [] as $row) {
                $label = $row['code'] ?: $row['name'];
                $rate = rtrim(rtrim(number_format((float) $row['rate'], 4, '.', ''), '0'), '.');
                $incl = ! empty($row['inclusive']) ? ' incl.' : '';
                $lines[] = "{$label} ({$rate}%{$incl}): ".$formatMoney((float) $row['amount']);
            }
        }
        $lines[] = 'Total: '.$formatMoney((float) $calc['total']);

        return $lines;
    }
}
