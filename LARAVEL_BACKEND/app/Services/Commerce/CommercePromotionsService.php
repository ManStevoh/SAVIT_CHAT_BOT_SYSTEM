<?php

namespace App\Services\Commerce;

use App\Models\Company;
use App\Models\Product;
use App\Models\StorefrontCoupon;
use App\Support\MoneyFormatter;
use Illuminate\Support\Carbon;

/**
 * Active sales + coupon snapshot for storefront banners and WhatsApp agent context.
 */
final class CommercePromotionsService
{
    /**
     * @return array{onSale: list<array<string, mixed>>, coupons: list<array<string, mixed>>, announcement: ?string}
     */
    public function snapshot(Company $company): array
    {
        $company->loadMissing('settings');
        $settings = $company->settings;
        $theme = is_array($company->storefront_theme) ? $company->storefront_theme : [];
        $announcement = isset($theme['announcement_bar']) && is_string($theme['announcement_bar'])
            ? trim($theme['announcement_bar'])
            : '';

        $onSale = Product::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'price', 'compare_at_price']);

        $now = Carbon::now();
        $coupons = StorefrontCoupon::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return [
            'announcement' => $announcement !== '' ? $announcement : null,
            'onSale' => $onSale->map(function (Product $p) use ($settings) {
                $price = (float) $p->price;
                $compare = (float) $p->compare_at_price;
                $pct = $compare > 0 ? (int) round((1 - ($price / $compare)) * 100) : 0;

                return [
                    'id' => (string) $p->id,
                    'name' => $p->name,
                    'price' => MoneyFormatter::formatFromSettings($price, $settings),
                    'compareAtPrice' => MoneyFormatter::formatFromSettings($compare, $settings),
                    'discountPercent' => $pct,
                ];
            })->values()->all(),
            'coupons' => $coupons->filter(fn (StorefrontCoupon $c) => $c->isCurrentlyValid())->map(function (StorefrontCoupon $c) {
                $label = $c->type === 'percent'
                    ? ((float) $c->value).'% off'
                    : ((float) $c->value).' off';

                return [
                    'code' => $c->code,
                    'label' => $label,
                    'type' => $c->type,
                    'value' => (float) $c->value,
                    'minOrder' => $c->min_order !== null ? (float) $c->min_order : null,
                    'endsAt' => $c->ends_at?->toIso8601String(),
                ];
            })->values()->all(),
        ];
    }

    public function agentPromptBlock(Company $company): string
    {
        $snap = $this->snapshot($company);
        $lines = ['Active promotions (authoritative — mention these when relevant; never invent discount codes):'];

        if ($snap['announcement']) {
            $lines[] = '- Storefront announcement: '.$snap['announcement'];
        }

        if ($snap['onSale'] !== []) {
            $lines[] = '- Products on sale (show sale price; mention previous price when helpful):';
            foreach ($snap['onSale'] as $item) {
                $pct = $item['discountPercent'] > 0 ? " ({$item['discountPercent']}% off)" : '';
                $lines[] = "  • {$item['name']}: now {$item['price']} (was {$item['compareAtPrice']}){$pct}";
            }
        } else {
            $lines[] = '- No products currently marked on sale (compare-at price > price).';
        }

        if ($snap['coupons'] !== []) {
            $lines[] = '- Valid storefront coupon codes customers can use at web checkout (and you may mention in chat):';
            foreach ($snap['coupons'] as $c) {
                $min = $c['minOrder'] !== null ? "; min order {$c['minOrder']}" : '';
                $ends = $c['endsAt'] ? '; ends '.$c['endsAt'] : '';
                $lines[] = "  • Code {$c['code']}: {$c['label']}{$min}{$ends}";
            }
        } else {
            $lines[] = '- No active storefront coupon codes right now.';
        }

        $lines[] = '- When quoting prices, use the current sale price. Do not invent Black Friday / promo codes that are not listed above.';

        return implode("\n", $lines);
    }
}
