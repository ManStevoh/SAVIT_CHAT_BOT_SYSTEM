<?php

namespace App\Services\AI;

use App\Models\Company;
use App\Models\Product;

/**
 * Validates OpenAI WhatsApp replies against the company catalog to reduce price hallucinations.
 * Redacts unknown prices instead of replacing the entire reply.
 */
class ReplyGuardService
{
    private const PRICE_TOLERANCE = 0.02;

    /** @var array<int, array<int, float>> */
    private array $catalogPriceCache = [];

    public function guard(Company $company, string $reply): string
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

    /**
     * @return array<int, float>
     */
    private function catalogPrices(Company $company): array
    {
        if (isset($this->catalogPriceCache[$company->id])) {
            return $this->catalogPriceCache[$company->id];
        }

        $products = Product::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->with(['variants' => fn ($q) => $q->where('status', 'active')])
            ->get();

        $prices = [];
        foreach ($products as $product) {
            if ($product->variants->isNotEmpty()) {
                foreach ($product->variants as $variant) {
                    $prices[] = round((float) $variant->price, 2);
                }
            } elseif ($product->price !== null) {
                $prices[] = round((float) $product->price, 2);
            }
        }

        return $this->catalogPriceCache[$company->id] = array_values(array_unique($prices));
    }

    private function shouldIgnoreNumber(float $value): bool
    {
        if ($value <= 0) {
            return true;
        }

        // Ignore calendar years and small ordinals (menu numbers, quantities).
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
