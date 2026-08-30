<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\SubscriptionOffer;
use App\Services\Platform\EntitlementService;
use App\Services\PlatformPayments\PlatformPaymentRegistry;
use App\Services\RegionalPricingService;
use App\Services\SubscriptionPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class PlanController extends Controller
{
    /**
     * List plans for public pricing page (no auth).
     * Currency is resolved from ?currency=, cookie, Cloudflare CF-IPCountry, or default.
     */
    public function index(Request $request, PlatformPaymentRegistry $platformRegistry, RegionalPricingService $pricing): JsonResponse
    {
        $context = $pricing->resolveFromRequest($request);
        $currency = $context['currency'];

        $availableDrivers = $platformRegistry->getAvailableDrivers();
        $methodsMap = [];
        foreach ($availableDrivers as $driver) {
            $methodsMap[$driver->getId()] = [
                'id' => $driver->getId(),
                'name' => $driver->getDisplayName(),
                'metadata' => $driver->getMetadata(),
            ];
        }

        $paystackEnabled = isset($methodsMap['paystack']);
        $paystackCurrency = $paystackEnabled
            ? strtoupper((string) (PaymentGateway::getConfig('paystack')['currency'] ?? 'NGN'))
            : null;

        $plans = Plan::orderBy('sort_order')->orderBy('id')->get();
        $offerPricing = app(SubscriptionPricingService::class);
        $publicOffers = SubscriptionOffer::query()->orderByDesc('id')->get();
        $data = $plans->map(function (Plan $p) use ($availableDrivers, $paystackCurrency, $pricing, $currency, $offerPricing, $publicOffers) {
            $planMethods = [];
            foreach ($availableDrivers as $driver) {
                if ($driver->getId() === 'stripe' && empty($p->stripe_price_id)) {
                    continue;
                }
                $amountForGateway = $pricing->amountForPlan(
                    $p,
                    strtoupper((string) ($driver->getMetadata()['currency'] ?? $currency))
                );
                $chargeable = $amountForGateway !== null ? (float) $amountForGateway : (float) ($p->price_amount ?? 0);
                if (($driver->getId() === 'mpesa' || $driver->getId() === 'paystack' || $driver->getId() === 'manual'
                    || $driver->getId() === 'pesapal' || $driver->getId() === 'flutterwave' || $driver->getId() === 'paypal')
                    && ($chargeable <= 0 || $p->is_free)) {
                    continue;
                }
                $planMethods[$driver->getId()] = true;
            }

            $limits = app(EntitlementService::class)->limitsForPlanSlug($p->slug);
            $amount = $pricing->amountForPlan($p, $currency);
            $listAmount = $amount !== null ? (float) $amount : null;
            $offer = $listAmount !== null
                ? $offerPricing->bestOfferForPlan($p, $currency, $listAmount, $publicOffers)
                : null;
            $saleAmount = null;
            $offerPayload = null;
            if ($offer && $listAmount !== null) {
                $discount = $offerPricing->computeDiscount($listAmount, $offer);
                $saleAmount = max(0, round($listAmount - $discount, 2));
                if ($saleAmount > 0 && $saleAmount < $listAmount) {
                    $offerPayload = $offerPricing->publicOfferPayload($offer, $listAmount);
                } else {
                    $saleAmount = null;
                }
            }

            return [
                'id' => (string) $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => $saleAmount !== null
                    ? $pricing->formatAmount($saleAmount, $currency)
                    : $pricing->displayForPlan($p, $currency),
                'priceAmount' => $listAmount,
                'originalPrice' => $offerPayload ? $pricing->displayForPlan($p, $currency) : null,
                'originalPriceAmount' => $offerPayload ? $listAmount : null,
                'salePriceAmount' => $saleAmount,
                'offer' => $offerPayload,
                'currency' => $currency,
                'paystackCurrency' => ! empty($planMethods['paystack']) ? $paystackCurrency : null,
                'description' => $p->description ?? '',
                'features' => $p->features ?? [],
                'entitlements' => [
                    'messages' => $limits['messages'],
                    'maxProducts' => $limits['max_products'] ?? null,
                    'team' => $limits['team'] ?? null,
                    'whatsappNumbers' => $limits['whatsapp_numbers'] ?? 1,
                    'apiAccess' => (bool) ($limits['api_access'] ?? false),
                    'analytics' => (bool) ($limits['analytics'] ?? false),
                    'analyticsLevel' => (string) ($limits['analytics_level'] ?? ($limits['analytics'] ? 'standard' : 'basic')),
                    'crmLevel' => (string) ($limits['crm_level'] ?? 'basic'),
                    'aiPostsPerMonth' => $limits['ai_posts_per_month'] ?? null,
                    'socialPlatforms' => $limits['social_platforms'] ?? null,
                    'allowPhysical' => (bool) ($limits['allow_physical'] ?? true),
                    'allowDigital' => (bool) ($limits['allow_digital'] ?? true),
                    'allowService' => (bool) ($limits['allow_service'] ?? false),
                    'allowBookings' => (bool) ($limits['allow_bookings'] ?? false),
                    'maxBookingsPerMonth' => array_key_exists('max_bookings_per_month', $limits)
                        ? ($limits['max_bookings_per_month'] === null ? null : (int) $limits['max_bookings_per_month'])
                        : 0,
                    'allowStorefront' => (bool) ($limits['allow_storefront'] ?? true),
                    'allowLinkInBio' => (bool) ($limits['allow_link_in_bio'] ?? true),
                    'allowDineIn' => (bool) ($limits['allow_dine_in'] ?? false),
                    'allowWhatsappCampaigns' => (bool) ($limits['allow_whatsapp_campaigns'] ?? true),
                    'requiresBranding' => (bool) ($limits['requires_branding'] ?? false),
                ],
                'popular' => (bool) $p->popular,
                'cta' => $p->cta ?? 'Start Free Trial',
                'hasTrial' => (bool) $p->has_trial,
                'trialDays' => $p->trial_days !== null ? (int) $p->trial_days : null,
                'isFree' => (bool) $p->is_free,
                'checkoutAvailable' => ! empty($planMethods),
                'paymentMethods' => $planMethods,
            ];
        });

        $payload = [
            'currency' => $currency,
            'currencyLabel' => $context['label'],
            'currencySymbol' => $context['symbol'],
            'detectedCountry' => $context['country'],
            'source' => $context['source'],
            'availableCurrencies' => $context['available'],
            'plans' => $data->values()->all(),
        ];

        $response = response()->json($payload);

        if ($context['source'] === RegionalPricingService::SOURCE_QUERY) {
            $cookieName = (string) config('pricing.cookie', 'pricing_currency');
            $minutes = max(60, (int) config('pricing.cookie_days', 30) * 24 * 60);
            $response->headers->setCookie(new Cookie(
                $cookieName,
                $currency,
                now()->addMinutes($minutes),
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                Cookie::SAMESITE_LAX
            ));
        }

        return $response;
    }
}
