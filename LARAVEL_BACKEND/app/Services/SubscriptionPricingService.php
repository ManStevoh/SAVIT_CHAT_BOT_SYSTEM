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
        $original = (float) ($plan->price_amount ?? 0);
        if ($original <= 0 || $plan->is_free) {
            return ['success' => false, 'message' => 'This plan is not available for paid checkout.'];
        }

        $currency = strtoupper($currency ?: (PaystackService::isEnabled()
            ? app(PaystackService::class)->getCurrency()
            : 'USD'));

        if ($couponCode === null || trim($couponCode) === '') {
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

        // Drop abandoned checkout holds so companies are not locked out of a code.
        CouponRedemption::where('company_id', $company->id)
            ->where('subscription_offer_id', $offer->id)
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(2))
            ->update(['status' => 'void']);

        $companyRedemptions = CouponRedemption::where('company_id', $company->id)
            ->where('subscription_offer_id', $offer->id)
            ->whereIn('status', ['applied', 'pending'])
            ->count();

        if ($companyRedemptions >= max(1, (int) $offer->max_per_company)) {
            return ['success' => false, 'message' => 'This coupon has already been used for your company.'];
        }

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

    public function computeDiscount(float $original, SubscriptionOffer $offer): float
    {
        if ($offer->discount_type === 'percent') {
            $pct = min(100, max(0, (float) $offer->discount_value));

            return round($original * ($pct / 100), 2);
        }

        return min($original, max(0, (float) $offer->discount_value));
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
