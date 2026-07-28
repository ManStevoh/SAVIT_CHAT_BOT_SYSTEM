<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\Storefront\StorefrontService;
use App\Support\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PublicStorefrontController extends Controller
{
    public function __construct(
        protected StorefrontService $storefront,
        protected OrderPaymentService $orderPayment,
    ) {}

    public function show(string $slug, Request $request): Response
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $this->maybeTrackReferral($company, $request);

        return Inertia::render('store/page', [
            'slug' => $slug,
            'company' => $this->companyPayload($company),
            'products' => $this->storefront->catalog($company),
            'cartCount' => $this->currentCartCount($company),
        ]);
    }

    public function product(string $slug, int $product): Response
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $productModel = $this->storefront->findActiveProduct($company, $product);
        if (! $productModel) {
            abort(404);
        }

        return Inertia::render('store/product', [
            'slug' => $slug,
            'company' => $this->companyPayload($company),
            'product' => $this->storefront->serializeProduct($productModel),
            'cartCount' => $this->currentCartCount($company),
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

        $this->storefront->addToCart(
            $session,
            (int) $validated['productId'],
            isset($validated['productVariantId']) ? (int) $validated['productVariantId'] : null,
            (int) ($validated['quantity'] ?? 1),
        );

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

        return Inertia::render('store/checkout', [
            'slug' => $slug,
            'company' => $this->companyPayload($company),
            'cart' => $cart,
            'dineInEnabled' => (bool) ($settings?->dine_in_enabled ?? false),
            'deliveryFeesEnabled' => (bool) ($settings?->delivery_fees_enabled ?? false),
            'presetDineInTableCode' => $request->query('table') ? (string) $request->query('table') : null,
        ]);
    }

    public function checkoutStore(string $slug, Request $request): RedirectResponse
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $session = $this->storefront->getSession($company, $this->cartToken($company));

        $validated = $request->validate([
            'customerName' => 'required|string|max:255',
            'customerPhone' => 'required|string|max:40',
            'fulfillmentType' => 'nullable|string|in:delivery,pickup,dine_in',
            'deliveryAddress' => 'nullable|string|max:1000',
            'dineInTableCode' => 'nullable|string|max:100',
        ]);

        try {
            $order = $this->storefront->placeOrder($company, $session, $validated);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()])->withInput();
        }

        return redirect()->to(url("/s/{$slug}/order/{$order->pay_token}"));
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
    protected function companyPayload(?Company $company): array
    {
        if (! $company) {
            return ['name' => 'Store'];
        }
        $company->loadMissing('settings');

        return [
            'name' => $company->name,
            'logo' => $company->logo ? asset('storage/'.$company->logo) : null,
            'currency' => $company->settings?->displayCurrencyCode() ?? 'USD',
            'moneyOptions' => $company->settings?->moneyDisplayOptions() ?? ['symbol' => null, 'thousands' => ',', 'decimal' => '.'],
            'whatsappNumber' => $company->settings?->whatsapp_number,
        ];
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
        $session = \App\Models\StorefrontSession::where('company_id', $company->id)
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
