<?php

namespace App\Services\Storefront;

use App\Models\Company;
use App\Models\DeliveryZone;
use App\Models\DineInTable;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StorefrontSession;
use App\Services\Orders\TaxCalculationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Powers the public storefront: catalog browsing, cart sessions, and checkout
 * into the same Order/OrderProduct pipeline used by the WhatsApp bot.
 */
class StorefrontService
{
    public function __construct(protected TaxCalculationService $taxCalculator) {}

    public function resolveCompanyBySlug(string $slug): Company
    {
        return Company::where('store_slug', $slug)
            ->where('storefront_enabled', true)
            ->firstOrFail();
    }

    /** @return Collection<int, Product> */
    public function activeProducts(Company $company): Collection
    {
        return Product::where('company_id', $company->id)
            ->where('status', 'active')
            ->with(['activeVariants', 'images'])
            ->orderBy('name')
            ->get();
    }

    public function findActiveProduct(Company $company, int $productId): ?Product
    {
        return Product::where('company_id', $company->id)
            ->where('id', $productId)
            ->where('status', 'active')
            ->with(['activeVariants', 'images'])
            ->first();
    }

    /** @return list<array<string, mixed>> */
    public function catalog(Company $company): array
    {
        return $this->activeProducts($company)
            ->map(fn (Product $p) => $this->serializeProduct($p))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function serializeProduct(Product $product): array
    {
        $images = $product->relationLoaded('images') ? $product->images : $product->images()->get();
        $variants = $product->relationLoaded('activeVariants') ? $product->activeVariants : $product->activeVariants()->get();

        $imageUrls = $images->map(fn ($img) => Storage::url($img->path))->values()->all();
        if ($imageUrls === [] && is_string($product->image) && $product->image !== '') {
            $imageUrls = [Storage::url($product->image)];
        }

        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => (float) $product->price,
            'category' => $product->category,
            'productType' => $product->product_type ?: 'physical',
            'trackInventory' => (bool) $product->track_inventory,
            'stock' => $product->stock !== null ? (int) $product->stock : null,
            'bookable' => (bool) $product->bookable,
            'images' => $imageUrls,
            'image' => $imageUrls[0] ?? null,
            'variants' => $variants->map(fn (ProductVariant $v) => [
                'id' => (string) $v->id,
                'label' => $v->label,
                'price' => (float) ($v->price ?? $product->price),
                'stock' => $v->stock !== null ? (int) $v->stock : null,
            ])->values()->all(),
        ];
    }

    public function getSession(Company $company, ?string $token): StorefrontSession
    {
        $session = $token
            ? StorefrontSession::where('company_id', $company->id)->where('session_token', $token)->first()
            : null;

        if (! $session) {
            $session = StorefrontSession::create([
                'company_id' => $company->id,
                'cart' => [],
                'fulfillment_type' => 'delivery',
            ]);
        }

        return $session;
    }

    public function addToCart(StorefrontSession $session, int $productId, ?int $variantId, int $quantity): StorefrontSession
    {
        $cart = $session->cart ?? [];
        $key = $productId.':'.($variantId ?: 0);
        $existing = $cart[$key] ?? ['product_id' => $productId, 'product_variant_id' => $variantId, 'quantity' => 0];
        $existing['quantity'] = max(1, (int) $existing['quantity'] + max(1, $quantity));
        $cart[$key] = $existing;
        $session->update(['cart' => $cart]);

        return $session;
    }

    public function setCartLineQuantity(StorefrontSession $session, string $key, int $quantity): StorefrontSession
    {
        $cart = $session->cart ?? [];
        if (! isset($cart[$key])) {
            return $session;
        }
        if ($quantity <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['quantity'] = $quantity;
        }
        $session->update(['cart' => $cart]);

        return $session;
    }

    public function removeCartLine(StorefrontSession $session, string $key): StorefrontSession
    {
        return $this->setCartLineQuantity($session, $key, 0);
    }

    public function clearCart(StorefrontSession $session): StorefrontSession
    {
        $session->update(['cart' => []]);

        return $session;
    }

    public function updateFulfillment(StorefrontSession $session, string $fulfillmentType, ?int $dineInTableId = null): StorefrontSession
    {
        $session->update([
            'fulfillment_type' => $fulfillmentType,
            'dine_in_table_id' => $dineInTableId,
        ]);

        return $session;
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   calcLines: list<array<string, mixed>>,
     *   subtotal: float,
     *   taxTotal: float,
     *   total: float,
     *   taxBreakdown: list<array<string, mixed>>,
     *   itemCount: int
     * }
     */
    public function cartSummary(Company $company, StorefrontSession $session): array
    {
        $cart = $session->cart ?? [];
        $items = [];
        $calcItems = [];

        foreach ($cart as $key => $line) {
            $product = Product::where('company_id', $company->id)->find($line['product_id'] ?? null);
            if (! $product) {
                continue;
            }
            $variant = ! empty($line['product_variant_id'])
                ? ProductVariant::where('product_id', $product->id)->find($line['product_variant_id'])
                : null;

            $price = (float) ($variant->price ?? $product->price);
            $qty = max(1, (int) ($line['quantity'] ?? 1));

            $items[] = [
                'key' => (string) $key,
                'productId' => (string) $product->id,
                'productVariantId' => $variant ? (string) $variant->id : null,
                'name' => $product->name.($variant ? ' - '.$variant->label : ''),
                'price' => $price,
                'quantity' => $qty,
                'image' => $this->firstImageUrl($product),
            ];

            $calcItems[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $price,
                'quantity' => $qty,
                'tax_rate_id' => $product->tax_rate_id,
            ];
        }

        $calc = $calcItems !== []
            ? $this->taxCalculator->calculateForCompany($company, $calcItems)
            : ['subtotal' => 0.0, 'tax_total' => 0.0, 'total' => 0.0, 'tax_breakdown' => [], 'lines' => []];

        foreach ($items as $idx => &$item) {
            $lineCalc = $calc['lines'][$idx] ?? null;
            $item['lineTotal'] = $lineCalc['line_total'] ?? round($item['price'] * $item['quantity'], 2);
            $item['lineSubtotal'] = $lineCalc['line_subtotal'] ?? $item['lineTotal'];
            $item['taxAmount'] = $lineCalc['tax_amount'] ?? 0.0;
        }
        unset($item);

        return [
            'items' => $items,
            'calcLines' => $calc['lines'],
            'subtotal' => (float) $calc['subtotal'],
            'taxTotal' => (float) $calc['tax_total'],
            'total' => (float) $calc['total'],
            'taxBreakdown' => $calc['tax_breakdown'],
            'itemCount' => array_sum(array_column($items, 'quantity')),
        ];
    }

    protected function firstImageUrl(Product $product): ?string
    {
        $primary = $product->primaryImage();
        if ($primary) {
            return Storage::url($primary->path);
        }
        if (is_string($product->image) && $product->image !== '') {
            return Storage::url($product->image);
        }

        return null;
    }

    public function deliveryFeeForCompany(Company $company, float $subtotal, string $fulfillmentType, ?string $deliveryAddress = null): float
    {
        if ($fulfillmentType !== 'delivery') {
            return 0.0;
        }

        if (class_exists(\App\Services\DeliveryFeeService::class)) {
            try {
                /** @phpstan-ignore-next-line class resolved only if present */
                return (float) app(\App\Services\DeliveryFeeService::class)->feeFor($company, $subtotal, $deliveryAddress);
            } catch (\Throwable) {
                // Fall through to built-in logic below.
            }
        }

        $company->loadMissing('settings');
        $settings = $company->settings;
        if (! $settings || ! $settings->delivery_fees_enabled) {
            return 0.0;
        }

        $fee = (float) $settings->default_delivery_fee;

        if ($deliveryAddress) {
            $zone = DeliveryZone::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->first(function (DeliveryZone $zone) use ($deliveryAddress) {
                    foreach (($zone->keywords ?? []) as $keyword) {
                        if ($keyword && stripos($deliveryAddress, (string) $keyword) !== false) {
                            return true;
                        }
                    }

                    return false;
                });
            if ($zone) {
                $fee = (float) $zone->fee;
            }
        }

        if ($settings->free_delivery_above !== null && $subtotal >= (float) $settings->free_delivery_above) {
            return 0.0;
        }

        return round(max(0, $fee), 2);
    }

    protected function isSpam(Company $company, string $customerPhone): bool
    {
        if (class_exists(\App\Services\SpamOrderGuard::class)) {
            try {
                /** @phpstan-ignore-next-line class resolved only if present */
                return (bool) app(\App\Services\SpamOrderGuard::class)->isSpam($company, $customerPhone);
            } catch (\Throwable) {
                return false;
            }
        }

        $company->loadMissing('settings');
        $settings = $company->settings;
        if (! $settings || ! $settings->spam_order_protection_enabled) {
            return false;
        }

        $hourly = Order::where('company_id', $company->id)
            ->where('customer_phone', $customerPhone)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        if ($hourly >= max(1, (int) $settings->spam_max_orders_per_hour)) {
            return true;
        }

        $daily = Order::where('company_id', $company->id)
            ->where('customer_phone', $customerPhone)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $daily >= max(1, (int) $settings->spam_max_orders_per_day);
    }

    /**
     * @param  array{customerName?: ?string, customerPhone?: ?string, deliveryAddress?: ?string, fulfillmentType?: ?string, dineInTableCode?: ?string}  $checkout
     */
    public function placeOrder(Company $company, StorefrontSession $session, array $checkout): Order
    {
        $summary = $this->cartSummary($company, $session);
        if ($summary['items'] === []) {
            throw new \RuntimeException('Your cart is empty.');
        }

        $fulfillmentType = $checkout['fulfillmentType'] ?? $session->fulfillment_type ?? 'delivery';
        if (! in_array($fulfillmentType, ['delivery', 'pickup', 'dine_in'], true)) {
            $fulfillmentType = 'delivery';
        }

        $customerName = trim((string) ($checkout['customerName'] ?? '')) ?: 'Customer';
        $customerPhone = trim((string) ($checkout['customerPhone'] ?? ''));

        $dineInTable = null;
        if ($fulfillmentType === 'dine_in') {
            $code = $checkout['dineInTableCode'] ?? null;
            if ($code) {
                $dineInTable = DineInTable::where('company_id', $company->id)
                    ->where('is_active', true)
                    ->where(function ($q) use ($code) {
                        $q->where('code', $code)->orWhere('qr_token', $code);
                    })
                    ->first();
            }
        }

        $deliveryAddress = $fulfillmentType === 'delivery' ? ($checkout['deliveryAddress'] ?? null) : null;
        if ($fulfillmentType === 'delivery' && (! $deliveryAddress || trim($deliveryAddress) === '')) {
            throw new \RuntimeException('Please provide a delivery address.');
        }

        $deliveryFee = $this->deliveryFeeForCompany($company, (float) $summary['subtotal'], $fulfillmentType, $deliveryAddress);

        $orderNumber = 'ORD-'.strtoupper(Str::random(8));
        while (Order::where('order_number', $orderNumber)->exists()) {
            $orderNumber = 'ORD-'.strtoupper(Str::random(8));
        }

        $spamFlagged = $customerPhone !== '' && $this->isSpam($company, $customerPhone);

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => null,
            'order_number' => $orderNumber,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone ?: null,
            'delivery_address' => $deliveryAddress,
            'fulfillment_type' => $fulfillmentType,
            'dine_in_table_id' => $dineInTable?->id,
            'dine_in_table_name' => $dineInTable?->name,
            'subtotal' => $summary['subtotal'],
            'tax_total' => $summary['taxTotal'],
            'tax_breakdown' => $summary['taxBreakdown'] !== [] ? $summary['taxBreakdown'] : null,
            'delivery_fee' => $deliveryFee,
            'total' => round($summary['total'] + $deliveryFee, 2),
            'status' => 'pending',
            'payment_status' => 'pending',
            'source' => 'storefront',
            'spam_flagged' => $spamFlagged,
        ]);

        foreach ($summary['items'] as $idx => $item) {
            $lineCalc = $summary['calcLines'][$idx] ?? [];
            $product = Product::find($item['productId']);

            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $item['productId'],
                'product_variant_id' => $item['productVariantId'],
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'tax_rate_id' => $lineCalc['tax_rate_id'] ?? null,
                'tax_name' => $lineCalc['tax_name'] ?? null,
                'tax_code' => $lineCalc['tax_code'] ?? null,
                'tax_rate' => $lineCalc['tax_rate'] ?? null,
                'tax_inclusive' => $lineCalc['tax_inclusive'] ?? false,
                'tax_amount' => $lineCalc['tax_amount'] ?? 0,
                'line_subtotal' => $item['lineSubtotal'],
                'fulfillment_data' => $product?->fulfillmentSnapshot(),
            ]);
        }

        $order->ensurePublicTokens();
        $this->clearCart($session);

        return $order;
    }
}
