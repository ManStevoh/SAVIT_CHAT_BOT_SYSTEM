<?php

namespace App\Services\AI;

use App\Models\Company;
use App\Models\Product;

/**
 * Validates AI replies against the company catalog to reduce hallucinations
 * (prices + in-stock claims for known product names).
 */
class ReplyGuardService
{
    private const PRICE_TOLERANCE = 0.02;

    /** @var array<int, array<int, float>> */
    private array $catalogPriceCache = [];

    /** @var array<int, array{names: list<string>, outOfStock: list<string>}> */
    private array $catalogMetaCache = [];

    public function guard(Company $company, string $reply): string
    {
        $reply = $this->guardPrices($company, $reply);

        return $this->guardStockClaims($company, $reply);
    }

    public function guardPrices(Company $company, string $reply): string
    {
        $allowed = $this->catalogPrices($company);
        if ($allowed === []) {
            return $reply;
        }

        if (! preg_match_all('/\b(\d{1,6}(?:[.,]\d{1,2})?)\b/u', $reply, $matches)) {
            return $reply;
        }

        $hadUnknown = false;
        $guarded = preg_replace_callback(
            '/\b(\d{1,6}(?:[.,]\d{1,2})?)\b/u',
            function (array $match) use ($allowed, &$hadUnknown) {
                $normalized = (float) str_replace(',', '.', $match[1]);
                if ($this->shouldIgnoreNumber($normalized)) {
                    return $match[0];
                }
                if ($this->priceIsKnown($normalized, $allowed)) {
                    return $match[0];
                }
                $hadUnknown = true;

                return 'see catalog for price';
            },
            $reply,
        );

        if (! $hadUnknown) {
            return $reply;
        }

        $guarded = trim(preg_replace('/\s+/', ' ', $guarded) ?? $guarded);

        return rtrim($guarded)
            ."\n\nReply \"prices\" for our full product list with exact amounts.";
    }

    public function guardStockClaims(Company $company, string $reply): string
    {
        $meta = $this->catalogMeta($company);
        if ($meta['outOfStock'] === []) {
            return $reply;
        }

        $lower = mb_strtolower($reply);
        $flagged = [];
        foreach ($meta['outOfStock'] as $name) {
            $needle = mb_strtolower($name);
            if ($needle === '' || ! str_contains($lower, $needle)) {
                continue;
            }
            if (preg_match('/\b(in stock|available now|ready to ship|we have)\b/iu', $reply)) {
                $flagged[] = $name;
            }
        }

        if ($flagged === []) {
            return $reply;
        }

        $unique = array_values(array_unique($flagged));

        return rtrim($reply)
            ."\n\nNote: ".implode(', ', $unique).' may be out of stock — please confirm availability before ordering.';
    }

    /**
     * @return array<int, float>
     */
    private function catalogPrices(Company $company): array
    {
        if (isset($this->catalogPriceCache[$company->id])) {
            return $this->catalogPriceCache[$company->id];
        }

        $this->loadCatalog($company);

        return $this->catalogPriceCache[$company->id] ?? [];
    }

    /**
     * @return array{names: list<string>, outOfStock: list<string>}
     */
    private function catalogMeta(Company $company): array
    {
        if (isset($this->catalogMetaCache[$company->id])) {
            return $this->catalogMetaCache[$company->id];
        }

        $this->loadCatalog($company);

        return $this->catalogMetaCache[$company->id] ?? ['names' => [], 'outOfStock' => []];
    }

    private function loadCatalog(Company $company): void
    {
        $products = Product::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->with(['variants' => fn ($q) => $q->where('status', 'active')])
            ->get();

        $prices = [];
        $names = [];
        $outOfStock = [];

        foreach ($products as $product) {
            $names[] = (string) $product->name;
            $stock = (int) ($product->stock ?? 0);
            if ($product->variants->isNotEmpty()) {
                $anyInStock = false;
                foreach ($product->variants as $variant) {
                    $prices[] = round((float) $variant->price, 2);
                    if ((int) ($variant->stock ?? 0) > 0) {
                        $anyInStock = true;
                    }
                }
                if (! $anyInStock) {
                    $outOfStock[] = (string) $product->name;
                }
            } else {
                if ($product->price !== null) {
                    $prices[] = round((float) $product->price, 2);
                }
                if ($stock <= 0) {
                    $outOfStock[] = (string) $product->name;
                }
            }
        }

        $this->catalogPriceCache[$company->id] = array_values(array_unique($prices));
        $this->catalogMetaCache[$company->id] = [
            'names' => $names,
            'outOfStock' => array_values(array_unique($outOfStock)),
        ];
    }

    private function shouldIgnoreNumber(float $value): bool
    {
        if ($value <= 0) {
            return true;
        }

        if ($value >= 1900 && $value <= 2100 && floor($value) === $value) {
            return true;
        }

        if ($value <= 20 && floor($value) === $value) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<int, float>  $allowed
     */
    private function priceIsKnown(float $price, array $allowed): bool
    {
        foreach ($allowed as $known) {
            if (abs($known - $price) <= self::PRICE_TOLERANCE) {
                return true;
            }
            if ($known >= 100 && abs($known - $price) <= 1.0) {
                return true;
            }
        }

        return false;
    }
}
