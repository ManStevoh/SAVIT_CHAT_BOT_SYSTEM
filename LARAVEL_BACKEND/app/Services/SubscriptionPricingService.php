<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CouponRedemption;
use App\Models\Plan;
use App\Models\SubscriptionOffer;
use App\Services\PaystackService;
use Illuminate\Support\Facades\DB;

/**
 * Resolve plan price with optional coupon/offer for subscription checkout.
 */
class SubscriptionPricingService
{
    /**
     * @return array{
     *   success: bool,
     *   message?: string,
     *   original_amount?: float,
     *   discount_amount?: float,
     *   final_amount?: float,
     *   currency?: string,
     *   offer?: SubscriptionOffer|null,
     *   code?: string|null
     * }
     */
    public function quote(Plan $plan, Company $company, ?string $couponCode = null, ?string $currency = null): array
    {
        $currency = strtoupper($currency ?: (PaystackService::isEnabled()
            ? app(PaystackService::class)->getCurrency()
            : (string) config('pricing.default_currency', 'USD')));

        $regional = app(RegionalPricingService::class);
        $resolvedAmount = $regional->amountForPlan($plan, $currency);
        $original = (float) ($resolvedAmount ?? $plan->price_amount ?? 0);
        if ($original <= 0 || $plan->is_free) {
            return ['success' => false, 'message' => 'This plan is not available for paid checkout.'];
        }

        if ($couponCode === null || trim($couponCode) === '') {
            $publicOffer = $this->bestOfferForPlan($plan, $currency, $original);
            if (! $publicOffer || ! $this->companyCanRedeem($company, $publicOffer)) {
                return [
                    'success' => true,
                    'original_amount' => $original,
                    'discount_amount' => 0.0,
                    'final_amount' => $original,
                    'currency' => $currency,
                    'offer' => null,
                    'code' => null,
                ];
            }

            return $this->quoteFromOffer($original, $currency, $publicOffer);
        }

        $offer = SubscriptionOffer::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($couponCode))])
            ->first();

        if (! $offer || ! $offer->isCurrentlyValid()) {
            return ['success' => false, 'message' => 'Invalid or expired coupon code.'];
        }

        if ($offer->plan_id && (int) $offer->plan_id !== (int) $plan->id) {
            return ['success' => false, 'message' => 'This coupon does not apply to the selected plan.'];
        }

        if ($offer->currency && strtoupper($offer->currency) !== $currency) {
            return ['success' => false, 'message' => 'This coupon is not valid for the current payment currency ('.$currency.').'];
        }

        if (! $this->companyCanRedeem($company, $offer)) {
            return ['success' => false, 'message' => 'This coupon has already been used for your company.'];
        }

        $quoted = $this->quoteFromOffer($original, $currency, $offer);
        if (! ($quoted['success'] ?? false)) {
            return $quoted;
        }

        return $quoted;
    }

    /**
     * Best currently-valid public offer for a plan + currency (no company redemption check).
     *
     * @param  iterable<SubscriptionOffer>|null  $offers
     */
    public function bestOfferForPlan(Plan $plan, string $currency, float $original, ?iterable $offers = null): ?SubscriptionOffer
    {
        if ($original <= 0 || $plan->is_free) {
            return null;
        }

        $currency = strtoupper($currency);
        $pool = $offers ?? SubscriptionOffer::query()->orderByDesc('id')->get();
        $best = null;
        $bestDiscount = 0.0;

        foreach ($pool as $offer) {
            if (! $offer instanceof SubscriptionOffer || ! $offer->isCurrentlyValid()) {
                continue;
            }
            if ($offer->plan_id && (int) $offer->plan_id !== (int) $plan->id) {
                continue;
            }
            if ($offer->currency && strtoupper((string) $offer->currency) !== $currency) {
                continue;
            }
            $discount = $this->computeDiscount($original, $offer);
            $final = max(0, round($original - $discount, 2));
            if ($discount <= 0 || $final <= 0) {
                continue;
            }
            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $best = $offer;
            }
        }

        return $best;
    }

    /**
     * @return array{code: string, name: string, discountType: string, discountValue: float, discountAmount: float}
     */
    public function publicOfferPayload(SubscriptionOffer $offer, float $original): array
    {
        return [
            'code' => $offer->code,
            'name' => $offer->name,
            'discountType' => $offer->discount_type,
            'discountValue' => (float) $offer->discount_value,
            'discountAmount' => $this->computeDiscount($original, $offer),
        ];
    }

    public function computeDiscount(float $original, SubscriptionOffer $offer): float
    {
        if ($offer->discount_type === 'percent') {
            $pct = min(100, max(0, (float) $offer->discount_value));

            return round($original * ($pct / 100), 2);
        }

        return min($original, max(0, (float) $offer->discount_value));
    }

    /**
     * @return array{
     *   success: bool,
     *   message?: string,
     *   original_amount?: float,
     *   discount_amount?: float,
     *   final_amount?: float,
     *   currency?: string,
     *   offer?: SubscriptionOffer,
     *   code?: string
     * }
     */
    private function quoteFromOffer(float $original, string $currency, SubscriptionOffer $offer): array
    {
        $discount = $this->computeDiscount($original, $offer);
        $final = max(0, round($original - $discount, 2));

        if ($final <= 0) {
            return ['success' => false, 'message' => 'Coupon would reduce the price to zero. Use a free plan or adjust the offer.'];
        }

        return [
            'success' => true,
            'original_amount' => $original,
            'discount_amount' => $discount,
            'final_amount' => $final,
            'currency' => $currency,
            'offer' => $offer,
            'code' => $offer->code,
        ];
    }

    private function companyCanRedeem(Company $company, SubscriptionOffer $offer): bool
    {
        CouponRedemption::where('company_id', $company->id)
            ->where('subscription_offer_id', $offer->id)
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(2))
            ->update(['status' => 'void']);

        $companyRedemptions = CouponRedemption::where('company_id', $company->id)
            ->where('subscription_offer_id', $offer->id)
            ->whereIn('status', ['applied', 'pending'])
            ->count();

        return $companyRedemptions < max(1, (int) $offer->max_per_company);
    }

    /**
     * Persist a pending redemption tied to a payment reference (completed on webhook).
     */
    public function reserveRedemption(
        SubscriptionOffer $offer,
        Company $company,
        string $paymentReference,
        float $original,
        float $discount,
        float $final,
        string $currency,
    ): CouponRedemption {
        return CouponRedemption::create([
            'subscription_offer_id' => $offer->id,
            'company_id' => $company->id,
            'payment_reference' => $paymentReference,
            'original_amount' => $original,
            'discount_amount' => $discount,
            'final_amount' => $final,
            'currency' => $currency,
            'status' => 'pending',
        ]);
    }

    public function completeRedemption(string $paymentReference, ?int $subscriptionId = null): void
    {
        DB::transaction(function () use ($paymentReference, $subscriptionId) {
            $redemption = CouponRedemption::where('payment_reference', $paymentReference)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $redemption) {
                return;
            }

            $redemption->update([
                'status' => 'applied',
                'subscription_id' => $subscriptionId,
            ]);

            SubscriptionOffer::where('id', $redemption->subscription_offer_id)
                ->increment('redemption_count');
        });
    }
}
