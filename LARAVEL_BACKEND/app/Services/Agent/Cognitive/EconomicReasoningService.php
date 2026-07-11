<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;

/**
 * Economic reasoning (#48) — margin, LTV, and stock-age aware guidance.
 */
final class EconomicReasoningService
{
    /**
     * @return array{margin_note: string, ltv_note: string, stock_note: string}
     */
    public function analyze(Company $company, ?string $customerPhone): array
    {
        $companyId = (int) $company->id;

        $avgOrder = (float) (Order::query()
            ->where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(90))
            ->avg('total') ?? 0);

        $ltv = 0.0;
        if ($customerPhone) {
            $ltv = (float) Order::query()
                ->where('company_id', $companyId)
                ->where('customer_phone', $customerPhone)
                ->where('payment_status', 'paid')
                ->sum('total');
        }

        $slowStock = Product::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('stock', '>', 10)
            ->count();

        return [
            'margin_note' => $avgOrder > 0
                ? 'Average paid order (90d): '.number_format($avgOrder, 0).'. Discounts should preserve margin.'
                : 'Limited order history — be conservative with discounts.',
            'ltv_note' => $ltv > 0
                ? "Customer lifetime value: {$ltv}. Higher LTV customers warrant more retention effort."
                : 'New or low-value customer — focus on conversion over deep discounts.',
            'stock_note' => $slowStock > 0
                ? "{$slowStock} products have high stock — consider promoting slow movers over discounting fast sellers."
                : 'Stock levels appear normal.',
        ];
    }

    public function financePerspective(Company $company, string $topic, string $risk): string
    {
        $notes = $this->analyze($company, null);

        if ($risk === 'price negotiation' || $topic === 'pricing') {
            return 'Finance: '.$notes['margin_note'].' '.$notes['ltv_note'].' Avoid unapproved discounts.';
        }

        return 'Finance: '.$notes['margin_note'];
    }

    /**
     * @param  array<string, mixed>  $perception
     */
    public function guidanceForPrompt(Company $company, ?string $customerPhone, array $perception): string
    {
        $notes = $this->analyze($company, $customerPhone);
        $parts = ['Economic context (internal):'];
        foreach ($notes as $note) {
            $parts[] = $note;
        }

        return implode("\n", $parts);
    }
}
