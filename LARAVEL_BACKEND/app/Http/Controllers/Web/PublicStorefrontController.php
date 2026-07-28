<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\StorefrontSession;
use App\Services\OrderPaymentService;
use App\Services\Storefront\StorefrontService;
use App\Support\MoneyFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PublicStorefrontController extends Controller
{
    /** Chrome/UI string sets for the storefront locale switcher (feature 18, en + sw stubs). */
    protected const CHROME_STRINGS = [
        'en' => ['cart' => 'Cart', 'checkout' => 'Checkout', 'search' => 'Search', 'trackOrder' => 'Track order'],
        'sw' => ['cart' => 'Kikapu', 'checkout' => 'Malipo', 'search' => 'Tafuta', 'trackOrder' => 'Fuatilia Oda'],
    ];

    public function __construct(
        protected StorefrontService $storefront,
        protected OrderPaymentService $orderPayment,
    ) {}

    public function show(string $slug, Request $request): Response
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $this->maybeTrackReferral($company, $request);
        $locale = $this->resolveLocale($company, $request);

        $filters = [
            'q' => $request->query('q'),
            'sort' => $request->query('sort'),
            'category' => $request->query('category'),
            'in_stock' => $request->query('in_stock'),
            'min_price' => $request->query('min_price'),
            'max_price' => $request->query('max_price'),
            'type' => $request->query('type'),
        ];
        $products = $this->storefront->catalogFiltered($company, $filters);
        $session = $this->storefront->getSession($company, $this->cartToken($company));
        $this->persistCartToken($company, $session->session_token);
        $this->storefront->recordEvent($company, 'view_catalog', $session->session_token);

        return Inertia::render('store/page', [
            'slug' => $slug,
            'company' => $this->companyPayload($company, $request),
            'products' => $products,
            'filters' => $filters,
            'sections' => $this->resolveSections($company, $products),
            'cartCount' => $this->currentCartCount($company),
            'wishlist' => $this->currentWishlist($company),
            'locale' => $locale,
            'chrome' => self::CHROME_STRINGS[$locale] ?? self::CHROME_STRINGS['en'],
            'seo' => [
                'title' => $company->name.' — Shop',
                'description' => 'Shop '.$company->name.' online.',
            ],
        ]);
    }

    public function product(string $slug, string $product, Request $request): Response
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $productModel = $this->storefront->findActiveProduct($company, $product);
        if (! $productModel) {
            abort(404);
        }
        $locale = $this->resolveLocale($company, $request);
        $session = $this->storefront->getSession($company, $this->cartToken($company));
        $this->persistCartToken($company, $session->session_token);
        $this->storefront->recordEvent($company, 'view_product', $session->session_token, $productModel->id);

        $productPath = $productModel->slug ?: (string) $productModel->id;

        return Inertia::render('store/product', [
            'slug' => $slug,
            'company' => $this->companyPayload($company, $request),
            'product' => $this->storefront->serializeProduct($productModel),
            'related' => $this->storefront->relatedProducts($company, $productModel),
            'cartCount' => $this->currentCartCount($company),
            'wishlist' => $this->currentWishlist($company),
            'shareUrl' => url("/s/{$slug}/p/{$productPath}"),
            'locale' => $locale,
            'chrome' => self::CHROME_STRINGS[$locale] ?? self::CHROME_STRINGS['en'],
            'seo' => [
                'title' => $productModel->meta_title ?: ($productModel->name.' — '.$company->name),
                'description' => $productModel->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $productModel->description), 160),
                'image' => $this->storefront->serializeProduct($productModel)['image'] ?? null,
            ],
        ]);
    }

    /**
     * Feature 17: toggle a product id in the session wishlist (JSON, no full page reload).
     */
    public function wishlistToggle(string $slug, Request $request): JsonResponse
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $session = $this->storefront->getSession($company, $this->cartToken($company));
        $this->persistCartToken($company, $session->session_token);

        $validated = $request->validate([
            'productId' => 'required|integer',
        ]);

        $wishlist = $this->storefront->toggleWishlist($session, (int) $validated['productId']);

        return response()->json([
            'success' => true,
            'wishlist' => array_map('strval', $wishlist),
        ]);
    }

    /**
     * Feature 16: shopper-submitted review — stored unapproved until a merchant approves it.
     */
    public function reviewStore(string $slug, string $product, Request $request): RedirectResponse
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $productModel = $this->storefront->findActiveProduct($company, $product);
        if (! $productModel) {
            abort(404);
        }

        $validated = $request->validate([
            'authorName' => 'required|string|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'body' => 'nullable|string|max:2000',
        ]);

        ProductReview::create([
            'company_id' => $company->id,
            'product_id' => $productModel->id,
            'author_name' => $validated['authorName'],
            'rating' => $validated['rating'],
            'body' => $validated['body'] ?? null,
            'is_approved' => false,
        ]);

        return back()->with('status', 'Thanks! Your review will appear once approved.');
    }

    /**
     * Feature 15: GET /s/{slug}/checkout/suggest?phone= — suggest the last saved address for a
     * known phone number, used to prefill the checkout form for return shoppers (no auth).
     */
    public function checkoutSuggest(string $slug, Request $request): JsonResponse
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $phone = $request->query('phone');

        return response()->json([
            'suggestedAddress' => $this->storefront->suggestedAddressForPhone($company, is_string($phone) ? $phone : null),
        ]);
    }

    public function cart(string $slug): Response
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $session = $this->storefront->getSession($company, $this->cartToken($company));
        $this->persistCartToken($company, $session->session_token);

        return Inertia::render('store/cart', [
            'slug' => $slug,
            'company' => $this->companyPayload($company),
            'cart' => $this->storefront->cartSummary($company, $session),
        ]);
    }

    public function cartAdd(string $slug, Request $request): RedirectResponse
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $session = $this->storefront->getSession($company, $this->cartToken($company));
        $this->persistCartToken($company, $session->session_token);

        $validated = $request->validate([
            'productId' => 'required|integer',
            'productVariantId' => 'nullable|integer',
            'quantity' => 'nullable|integer|min:1|max:999',
        ]);

        $product = $this->storefront->findActiveProduct($company, (int) $validated['productId']);
        if (! $product) {
            return back()->withErrors(['productId' => 'Product not found.']);
        }

        try {
            $this->storefront->addToCart(
                $session,
                (int) $validated['productId'],
                isset($validated['productVariantId']) ? (int) $validated['productVariantId'] : null,
                (int) ($validated['quantity'] ?? 1),
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['productId' => $e->getMessage()]);
        }

        $this->storefront->recordEvent($company, 'add_to_cart', $session->session_token, $product->id);

        return redirect()->to(url("/s/{$slug}/cart"));
    }

    public function cartUpdate(string $slug, Request $request): RedirectResponse
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $session = $this->storefront->getSession($company, $this->cartToken($company));

        $validated = $request->validate([
            'key' => 'required|string',
            'quantity' => 'required|integer|min:0|max:999',
        ]);

        $this->storefront->setCartLineQuantity($session, $validated['key'], (int) $validated['quantity']);

        return redirect()->to(url("/s/{$slug}/cart"));
    }

    public function cartClear(string $slug): RedirectResponse
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $session = $this->storefront->getSession($company, $this->cartToken($company));
        $this->storefront->clearCart($session);

        return redirect()->to(url("/s/{$slug}/cart"));
    }

    public function checkout(string $slug, Request $request): Response|RedirectResponse
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $session = $this->storefront->getSession($company, $this->cartToken($company));
        $cart = $this->storefront->cartSummary($company, $session);

        if ($cart['items'] === []) {
            return redirect()->to(url("/s/{$slug}/cart"));
        }

        $company->loadMissing('settings');
        $settings = $company->settings;
        $locale = $this->resolveLocale($company, $request);

        // Feature 15: suggest the last saved address for a known phone (query param or session).
        $phone = $request->query('phone') ?? $session->customer_phone;
        $this->storefront->recordEvent($company, 'begin_checkout', $session->session_token);

        return Inertia::render('store/checkout', [
            'slug' => $slug,
            'company' => $this->companyPayload($company, $request),
            'cart' => $cart,
            'dineInEnabled' => (bool) ($settings?->dine_in_enabled ?? false),
            'deliveryFeesEnabled' => (bool) ($settings?->delivery_fees_enabled ?? false),
            'presetDineInTableCode' => $request->query('table') ? (string) $request->query('table') : null,
            'suggestedAddress' => $this->storefront->suggestedAddressForPhone($company, is_string($phone) ? $phone : null),
            'locale' => $locale,
            'chrome' => self::CHROME_STRINGS[$locale] ?? self::CHROME_STRINGS['en'],
        ]);
    }

    public function checkoutStore(string $slug, Request $request): RedirectResponse
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $session = $this->storefront->getSession($company, $this->cartToken($company));

        $validated = $request->validate([
            'customerName' => 'required|string|max:255',
            'customerPhone' => 'required|string|max:40',
            'customerEmail' => 'nullable|email|max:255',
            'fulfillmentType' => 'nullable|string|in:delivery,pickup,dine_in',
            'deliveryAddress' => 'nullable|string|max:1000',
            'dineInTableCode' => 'nullable|string|max:100',
            'orderNotes' => 'nullable|string|max:1000',
            'giftMessage' => 'nullable|string|max:500',
            'tipAmount' => 'nullable|numeric|min:0',
            'couponCode' => 'nullable|string|max:64',
        ]);

        $session->update([
            'customer_name' => $validated['customerName'] ?? null,
            'customer_phone' => $validated['customerPhone'] ?? null,
            'customer_email' => $validated['customerEmail'] ?? null,
            'last_activity_at' => now(),
        ]);

        try {
            $order = $this->storefront->placeOrder($company, $session, $validated);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()])->withInput();
        }

        $this->storefront->recordEvent($company, 'purchase', $session->session_token, null, [
            'order_id' => $order->id,
            'total' => (float) $order->total,
        ]);

        return redirect()->to(url("/s/{$slug}/order/{$order->pay_token}"));
    }

    public function checkoutQuote(string $slug, Request $request): JsonResponse
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $session = $this->storefront->getSession($company, $this->cartToken($company));
        $this->persistCartToken($company, $session->session_token);

        $validated = $request->validate([
            'fulfillmentType' => 'nullable|string|in:delivery,pickup,dine_in',
            'deliveryAddress' => 'nullable|string|max:1000',
            'couponCode' => 'nullable|string|max:64',
            'tipAmount' => 'nullable|numeric|min:0',
        ]);

        return response()->json($this->storefront->quoteCheckout($company, $session, $validated));
    }

    public function track(string $slug): Response
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);

        return Inertia::render('store/track', [
            'slug' => $slug,
            'company' => $this->companyPayload($company),
            'order' => null,
            'notFound' => false,
        ]);
    }

    public function trackLookup(string $slug, Request $request): Response
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $validated = $request->validate([
            'phone' => 'required|string|max:40',
            'orderNumber' => 'required|string|max:64',
        ]);

        $order = $this->storefront->trackOrder($company, $validated['phone'], $validated['orderNumber']);

        return Inertia::render('store/track', [
            'slug' => $slug,
            'company' => $this->companyPayload($company),
            'order' => $order ? $this->orderPayload($order) : null,
            'notFound' => $order === null,
        ]);
    }

    public function confirmation(string $slug, string $order): Response
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $orderModel = Order::where('company_id', $company->id)
            ->where(function ($q) use ($order) {
                $q->where('pay_token', $order);
                if (ctype_digit($order)) {
                    $q->orWhere('id', (int) $order);
                }
            })
            ->with('orderProducts')
            ->firstOrFail();

        return Inertia::render('store/confirmation', [
            'slug' => $slug,
            'company' => $this->companyPayload($company),
            'order' => $this->orderPayload($orderModel),
        ]);
    }

    public function bio(string $slug, Request $request): Response
    {
        $company = Company::where('store_slug', $slug)
            ->where('link_in_bio_enabled', true)
            ->firstOrFail();
        $this->maybeTrackReferral($company, $request);

        $company->loadMissing('settings');

        return Inertia::render('bio/page', [
            'slug' => $slug,
            'company' => [
                'name' => $company->name,
                'headline' => $company->link_in_bio_headline,
                'bio' => $company->link_in_bio_bio,
                'logo' => $company->logo ? asset('storage/'.$company->logo) : null,
                'links' => is_array($company->link_in_bio_links) ? array_values($company->link_in_bio_links) : [],
                'whatsappNumber' => $company->settings?->whatsapp_number,
                'storefrontEnabled' => (bool) $company->storefront_enabled,
                'storeSlug' => $company->store_slug,
            ],
        ]);
    }

    public function pay(string $token): Response
    {
        $order = Order::where('pay_token', $token)->with(['orderProducts', 'company.settings'])->firstOrFail();

        return Inertia::render('pay/page', [
            'token' => $token,
            'order' => $this->orderPayload($order),
            'company' => $this->companyPayload($order->company),
            'paymentOptions' => $this->paymentOptions($order),
        ]);
    }

    public function payAction(string $token, Request $request): RedirectResponse
    {
        $order = Order::where('pay_token', $token)->with(['company.settings'])->firstOrFail();

        $validated = $request->validate([
            'method' => 'required|string|in:cod,stripe,paystack,mpesa,bank_transfer',
            'phone' => 'nullable|string|max:40',
        ]);

        switch ($validated['method']) {
            case 'cod':
                $order->update(['payment_method' => 'cod', 'status' => 'confirmed']);

                return redirect()->to(url("/pay/{$token}"))->with('status', 'Order confirmed for cash on delivery.');

            case 'stripe':
                $result = $this->orderPayment->createStripePaymentLinkForOrder($order);
                if ($result['success'] && ! empty($result['url'])) {
                    return redirect()->away($result['url']);
                }

                return back()->withErrors(['method' => $result['error'] ?? 'Could not start card payment.']);

            case 'paystack':
                $result = $this->orderPayment->createPaystackPaymentLinkForOrder($order);
                if ($result['success'] && ! empty($result['url'])) {
                    return redirect()->away($result['url']);
                }

                return back()->withErrors(['method' => $result['error'] ?? 'Could not start payment.']);

            case 'mpesa':
                $phone = $validated['phone'] ?? $order->customer_phone;
                if (! $phone) {
                    return back()->withErrors(['phone' => 'Phone number is required for M-Pesa.']);
                }
                $result = $this->orderPayment->sendStkPushForOrder($order, $phone);
                if ($result['success']) {
                    return redirect()->to(url("/pay/{$token}"))->with('status', 'M-Pesa prompt sent. Enter your PIN to complete payment.');
                }

                return back()->withErrors(['method' => $result['error'] ?? 'Could not send M-Pesa prompt.']);

            case 'bank_transfer':
                $order->update(['payment_method' => 'bank_transfer']);

                return redirect()->to(url("/pay/{$token}"))->with('status', 'Bank transfer instructions shown below.');
        }

        return back();
    }

    public function invoice(string $token): Response
    {
        $order = Order::where('invoice_token', $token)->with(['orderProducts', 'company.settings'])->firstOrFail();

        return Inertia::render('invoice/page', [
            'token' => $token,
            'order' => $this->orderPayload($order),
            'company' => $this->companyPayload($order->company),
        ]);
    }

    /** @return array<string, mixed> */
    protected function companyPayload(?Company $company, ?Request $request = null): array
    {
        if (! $company) {
            return ['name' => 'Store'];
        }
        $company->loadMissing('settings');
        $settings = $company->settings;
        $baseCurrency = $settings?->displayCurrencyCode() ?? 'USD';
        $altCurrencies = $settings?->altCurrencyOptions() ?? [];

        // Feature 18: `?currency=` picks an alt display currency for formatPrice only — the
        // charged total always stays in the base currency.
        $requestedCurrency = $request?->query('currency');
        $displayCurrency = $baseCurrency;
        $displayRate = 1.0;
        if (is_string($requestedCurrency) && $altCurrencies !== []) {
            foreach ($altCurrencies as $alt) {
                if (strcasecmp($alt['code'], $requestedCurrency) === 0) {
                    $displayCurrency = $alt['code'];
                    $displayRate = $alt['rate'];
                    break;
                }
            }
        }

        return [
            'name' => $company->name,
            'logo' => $company->logo ? asset('storage/'.$company->logo) : null,
            'currency' => $baseCurrency,
            'moneyOptions' => $settings?->moneyDisplayOptions() ?? ['symbol' => null, 'thousands' => ',', 'decimal' => '.'],
            'whatsappNumber' => $settings?->whatsapp_number,
            'whatsappUrl' => $this->whatsappUrl($settings?->whatsapp_number, 'Hi, I\'m interested in '.$company->name),
            'altCurrencies' => $altCurrencies,
            'displayCurrency' => $displayCurrency,
            'displayRate' => $displayRate,
            'theme' => is_array($company->storefront_theme) ? $company->storefront_theme : [],
            'customDomain' => $company->custom_domain,
        ];
    }

    protected function whatsappUrl(?string $number, string $text): ?string
    {
        if (! $number) {
            return null;
        }
        $digits = preg_replace('/[^0-9]/', '', $number) ?? '';
        if ($digits === '') {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }

    /**
     * Feature 18: resolve + persist the storefront chrome locale (`?lang=` wins, else session,
     * else the merchant's default). Catalog/product content itself stays in the merchant's
     * language — only UI chrome strings are translated.
     */
    protected function resolveLocale(Company $company, Request $request): string
    {
        $key = 'storefront_locale_'.$company->id;
        $supported = array_keys(self::CHROME_STRINGS);

        $query = $request->query('lang');
        if (is_string($query) && in_array($query, $supported, true)) {
            session([$key => $query]);

            return $query;
        }

        $stored = session($key);
        if (is_string($stored) && in_array($stored, $supported, true)) {
            return $stored;
        }

        $company->loadMissing('settings');
        $default = $company->settings?->storefront_default_locale;

        return is_string($default) && in_array($default, $supported, true) ? $default : 'en';
    }

    /**
     * Feature 20: resolve the home page section list, defaulting to a flat catalog grid, and
     * hydrate `featured_products` sections with matching serialized products.
     *
     * @param  list<array<string, mixed>>  $catalogProducts
     * @return list<array<string, mixed>>
     */
    protected function resolveSections(Company $company, array $catalogProducts): array
    {
        $sections = is_array($company->storefront_sections) && $company->storefront_sections !== []
            ? $company->storefront_sections
            : [['type' => 'catalog']];

        return array_values(array_map(function ($section) use ($catalogProducts) {
            if (! is_array($section)) {
                return ['type' => 'catalog'];
            }
            if (($section['type'] ?? null) === 'featured_products') {
                $ids = array_map('strval', $section['product_ids'] ?? []);
                $section['products'] = array_values(array_filter(
                    $catalogProducts,
                    fn ($p) => in_array((string) $p['id'], $ids, true)
                ));
            }

            return $section;
        }, $sections));
    }

    /** @return list<string> */
    protected function currentWishlist(Company $company): array
    {
        $token = $this->cartToken($company);
        if (! $token) {
            return [];
        }
        $session = StorefrontSession::where('company_id', $company->id)
            ->where('session_token', $token)
            ->first();

        return $session ? array_map('strval', $session->wishlistIds()) : [];
    }

    /** @return array<string, mixed> */
    protected function orderPayload(Order $order): array
    {
        $company = $order->company;
        $settings = $company?->settings;

        return [
            'id' => (string) $order->id,
            'orderNumber' => $order->order_number,
            'customerName' => $order->customer_name,
            'customerPhone' => $order->customer_phone,
            'deliveryAddress' => $order->delivery_address,
            'fulfillmentType' => $order->fulfillment_type,
            'status' => $order->status,
            'paymentStatus' => $order->payment_status,
            'paymentMethod' => $order->payment_method,
            'subtotal' => (float) $order->subtotal,
            'taxTotal' => (float) $order->tax_total,
            'deliveryFee' => (float) $order->delivery_fee,
            'discountTotal' => (float) ($order->discount_total ?? 0),
            'tipAmount' => (float) ($order->tip_amount ?? 0),
            'giftMessage' => $order->gift_message,
            'orderNotes' => $order->order_notes,
            'couponCode' => $order->coupon_code,
            'customerEmail' => $order->customer_email,
            'total' => (float) $order->total,
            'totalFormatted' => MoneyFormatter::formatFromSettings((float) $order->total, $settings),
            'createdAt' => $order->created_at?->toIso8601String(),
            'payToken' => $order->pay_token,
            'invoiceToken' => $order->invoice_token,
            'items' => $order->orderProducts->map(fn ($line) => [
                'name' => $line->name,
                'quantity' => (int) $line->quantity,
                'price' => (float) $line->price,
                'lineSubtotal' => (float) $line->line_subtotal,
            ])->values()->all(),
        ];
    }

    /** @return array{cod: bool, stripe: bool, paystack: bool, mpesa: bool, bankTransfer: bool, bankTransferInstructions: ?string} */
    protected function paymentOptions(Order $order): array
    {
        $settings = $order->company?->settings;

        return [
            'cod' => (bool) ($settings?->orders_accept_cod ?? false),
            'stripe' => (bool) ($settings?->orders_accept_stripe ?? false),
            'paystack' => (bool) ($settings?->orders_accept_paystack ?? false),
            'mpesa' => (bool) ($settings?->orders_accept_mpesa ?? false),
            'bankTransfer' => (bool) ($settings?->orders_accept_bank_transfer ?? false),
            'bankTransferInstructions' => $settings?->hasBankTransferInstructions() ? $settings->bank_transfer_instructions : null,
        ];
    }

    protected function cartToken(Company $company): ?string
    {
        return session('storefront_cart_'.$company->id);
    }

    protected function persistCartToken(Company $company, string $token): void
    {
        session(['storefront_cart_'.$company->id => $token]);
    }

    protected function currentCartCount(Company $company): int
    {
        $token = $this->cartToken($company);
        if (! $token) {
            return 0;
        }
        $session = StorefrontSession::where('company_id', $company->id)
            ->where('session_token', $token)
            ->first();
        if (! $session) {
            return 0;
        }

        return array_sum(array_map(fn ($line) => (int) ($line['quantity'] ?? 0), $session->cart ?? []));
    }

    protected function maybeTrackReferral(Company $company, Request $request): void
    {
        $ref = $request->query('ref');
        if (! $ref || ! is_string($ref)) {
            return;
        }
        if (! class_exists(\App\Services\Growth\AttributionService::class)) {
            return;
        }

        try {
            $link = \App\Models\AttributionLink::where('slug', $ref)->where('company_id', $company->id)->first();
            if ($link) {
                app(\App\Services\Growth\AttributionService::class)->recordClick($link, $request);
            }
        } catch (\Throwable $e) {
            Log::warning('Storefront referral tracking failed', ['error' => $e->getMessage()]);
        }
    }
}
