<?php

namespace App\Services\Storefront;

use App\Models\Company;
use App\Models\DeliveryZone;
use App\Models\DineInTable;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductBundleItem;
use App\Models\ProductRelationship;
use App\Models\ProductVariant;
use App\Models\StorefrontAddress;
use App\Models\StorefrontCoupon;
use App\Models\StorefrontCustomer;
use App\Models\StorefrontEvent;
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
    public function __construct(
        protected TaxCalculationService $taxCalculator,
        protected StorefrontWhatsAppBridgeService $whatsappBridge,
    ) {}

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

    /**
     * Resolve a public-facing product by slug (preferred) or numeric id.
     */
    public function findActiveProduct(Company $company, string|int $identifier): ?Product
    {
        $identifier = (string) $identifier;

        $query = Product::where('company_id', $company->id)
            ->where('status', 'active')
            ->with(['activeVariants', 'images']);

        if (ctype_digit($identifier)) {
            $query->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier)->orWhere('id', (int) $identifier);
            });
        } else {
            $query->where('slug', $identifier);
        }

        return $query->first();
    }

    /** @return list<array<string, mixed>> */
    public function catalog(Company $company): array
    {
        return $this->catalogFiltered($company, []);
    }

    /**
     * @param  array{q?: ?string, sort?: ?string, category?: ?string, in_stock?: bool|string|null, min_price?: mixed, max_price?: mixed, type?: ?string}  $filters
     * @return list<array<string, mixed>>
     */
    public function catalogFiltered(Company $company, array $filters = []): array
    {
        $query = Product::where('company_id', $company->id)
            ->where('status', 'active')
            ->with(['activeVariants', 'images']);

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $category = $filters['category'] ?? null;
        if (is_string($category) && $category !== '' && strtolower($category) !== 'all') {
            $query->where('category', $category);
        }

        $type = $filters['type'] ?? null;
        if (is_string($type) && $type !== '' && strtolower($type) !== 'all') {
            $query->where('product_type', $type);
        }

        $minPrice = $filters['min_price'] ?? null;
        if ($minPrice !== null && $minPrice !== '') {
            $query->where('price', '>=', (float) $minPrice);
        }

        $maxPrice = $filters['max_price'] ?? null;
        if ($maxPrice !== null && $maxPrice !== '') {
            $query->where('price', '<=', (float) $maxPrice);
        }

        $inStock = filter_var($filters['in_stock'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($inStock) {
            $query->where(function ($sub) {
                $sub->where('track_inventory', false)
                    ->orWhereNull('track_inventory')
                    ->orWhere(function ($sub2) {
                        $sub2->where('track_inventory', true)->where('stock', '>', 0);
                    });
            });
        }

        switch ($filters['sort'] ?? null) {
            case 'price_asc':
                $query->orderBy('price')->orderBy('name');
                break;
            case 'price_desc':
                $query->orderByDesc('price')->orderBy('name');
                break;
            case 'newest':
                $query->orderByDesc('created_at')->orderBy('name');
                break;
            case 'name_asc':
            default:
                $query->orderBy('name');
                break;
        }

        return $query->get()
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
        $imageGallery = $images->map(fn ($img) => [
            'url' => Storage::url($img->path),
            'alt' => $img->alt_text ?: $product->name,
        ])->values()->all();
        if ($imageUrls === [] && is_string($product->image) && $product->image !== '') {
            $imageUrls = [Storage::url($product->image)];
            $imageGallery = [['url' => Storage::url($product->image), 'alt' => $product->name]];
        }

        $price = (float) $product->price;
        $compareAtPrice = $product->compare_at_price !== null ? (float) $product->compare_at_price : null;
        $onSale = $compareAtPrice !== null && $compareAtPrice > $price;
        $discountPercent = $onSale && $compareAtPrice > 0
            ? (int) round((1 - ($price / $compareAtPrice)) * 100)
            : null;

        $approvedReviews = $product->relationLoaded('approvedReviews')
            ? $product->approvedReviews
            : $product->approvedReviews()->get();
        $reviewCount = $approvedReviews->count();
        $averageRating = $reviewCount > 0 ? round($approvedReviews->avg('rating'), 1) : null;

        $trackInventory = (bool) $product->track_inventory;
        $stock = $product->stock !== null ? (int) $product->stock : null;
        $soldOut = $trackInventory && $stock !== null && $stock <= 0;
        $lowStock = $trackInventory && $stock !== null && $stock > 0 && $stock <= 3;
        $maxQty = $trackInventory && $stock !== null ? max(0, $stock) : 999;

        $bundleItems = [];
        if ($product->product_type === 'bundle') {
            $items = $product->relationLoaded('bundleItems') ? $product->bundleItems : $product->bundleItems()->with('child')->get();
            $bundleItems = $items->map(function (ProductBundleItem $item) {
                $child = $item->child;

                return [
                    'productId' => (string) $item->child_product_id,
                    'name' => $child?->name ?? 'Item',
                    'quantity' => (int) $item->quantity,
                ];
            })->values()->all();
        }

        return [
            'id' => (string) $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'description' => $product->description,
            'metaTitle' => $product->meta_title,
            'metaDescription' => $product->meta_description,
            'price' => $price,
            'compareAtPrice' => $compareAtPrice,
            'onSale' => $onSale,
            'discountPercent' => $discountPercent,
            'category' => $product->category,
            'productType' => $product->product_type ?: 'physical',
            'trackInventory' => $trackInventory,
            'stock' => $stock,
            'soldOut' => $soldOut,
            'lowStock' => $lowStock,
            'maxQty' => $maxQty,
            'bookable' => (bool) $product->bookable,
            'images' => $imageUrls,
            'imageGallery' => $imageGallery,
            'image' => $imageUrls[0] ?? null,
            'variants' => $variants->map(fn (ProductVariant $v) => [
                'id' => (string) $v->id,
                'label' => $v->label,
                'price' => (float) ($v->price ?? $product->price),
                'stock' => $v->stock !== null ? (int) $v->stock : null,
                'soldOut' => $trackInventory && $v->stock !== null && (int) $v->stock <= 0,
            ])->values()->all(),
            'isSubscription' => (bool) $product->is_subscription,
            'subscriptionInterval' => $product->subscription_interval,
            'subscriptionIntervalLabel' => $product->subscriptionIntervalLabel(),
            'bundleItems' => $bundleItems,
            'averageRating' => $averageRating,
            'reviewCount' => $reviewCount,
            'reviews' => $approvedReviews->map(fn ($review) => [
                'id' => (string) $review->id,
                'authorName' => $review->author_name,
                'rating' => (int) $review->rating,
                'body' => $review->body,
                'createdAt' => $review->created_at?->toIso8601String(),
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
        $product = Product::where('company_id', $session->company_id)->find($productId);
        if (! $product) {
            throw new \RuntimeException('Product not found.');
        }

        $variant = $variantId ? ProductVariant::where('product_id', $productId)->find($variantId) : null;

        $cart = $session->cart ?? [];
        $key = $productId.':'.($variantId ?: 0);
        $existing = $cart[$key] ?? ['product_id' => $productId, 'product_variant_id' => $variantId, 'quantity' => 0];
        $newQuantity = max(1, (int) $existing['quantity'] + max(1, $quantity));

        $this->assertStockAvailable($product, $variant, $newQuantity);

        $existing['quantity'] = $newQuantity;
        $cart[$key] = $existing;
        $session->update([
            'cart' => $cart,
            'last_activity_at' => now(),
            'abandoned_notified_at' => null,
        ]);

        return $session;
    }

    /**
     * Enforce `track_inventory` stock limits for a cart line, throwing a friendly
     * exception when the requested quantity would oversell.
     */
    protected function assertStockAvailable(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if (! $product->track_inventory) {
            return;
        }

        $stock = $variant && $variant->stock !== null
            ? (int) $variant->stock
            : ($product->stock !== null ? (int) $product->stock : null);

        if ($stock === null) {
            return;
        }

        if ($quantity > $stock) {
            $label = $variant ? $product->name.' - '.$variant->label : $product->name;
            if ($stock <= 0) {
                throw new \RuntimeException("{$label} is sold out.");
            }

            throw new \RuntimeException("Only {$stock} left in stock for {$label}.");
        }
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
            $line = $cart[$key];
            $product = Product::where('company_id', $session->company_id)->find($line['product_id'] ?? null);
            if ($product) {
                $variant = ! empty($line['product_variant_id'])
                    ? ProductVariant::where('product_id', $product->id)->find($line['product_variant_id'])
                    : null;
                $this->assertStockAvailable($product, $variant, $quantity);
            }
            $cart[$key]['quantity'] = $quantity;
        }
        $session->update([
            'cart' => $cart,
            'last_activity_at' => now(),
            'abandoned_notified_at' => null,
        ]);

        return $session;
    }

    public function removeCartLine(StorefrontSession $session, string $key): StorefrontSession
    {
        return $this->setCartLineQuantity($session, $key, 0);
    }

    public function clearCart(StorefrontSession $session): StorefrontSession
    {
        $session->update([
            'cart' => [],
            'last_activity_at' => now(),
        ]);

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

    /** @return list<int> Updated wishlist product ids. */
    public function toggleWishlist(StorefrontSession $session, int $productId): array
    {
        $wishlist = $session->wishlistIds();
        if (in_array($productId, $wishlist, true)) {
            $wishlist = array_values(array_filter($wishlist, fn ($id) => $id !== $productId));
        } else {
            $wishlist[] = $productId;
        }
        $session->update(['wishlist' => $wishlist]);

        return $wishlist;
    }

    /**
     * Find or create a lightweight storefront customer identity from a phone number,
     * used to speed up return-visitor checkout (feature 15, v1 light — no auth).
     */
    public function findOrCreateCustomer(Company $company, ?string $phone, ?string $name = null, ?string $email = null): ?StorefrontCustomer
    {
        $phone = $phone !== null ? trim($phone) : '';
        if ($phone === '') {
            return null;
        }

        $customer = StorefrontCustomer::firstOrNew([
            'company_id' => $company->id,
            'phone' => $phone,
        ]);

        if ($name && trim($name) !== '') {
            $customer->name = trim($name);
        }
        if ($email && trim($email) !== '') {
            $customer->email = trim($email);
        }
        $customer->last_order_at = now();
        $customer->save();

        return $customer;
    }

    /**
     * @return array{line: string, city: ?string, label: ?string, customerName: ?string}|null
     */
    public function suggestedAddressForPhone(Company $company, ?string $phone): ?array
    {
        $phone = $phone !== null ? trim($phone) : '';
        if ($phone === '') {
            return null;
        }

        $customer = StorefrontCustomer::where('company_id', $company->id)->where('phone', $phone)->first();
        if (! $customer) {
            return null;
        }

        $address = StorefrontAddress::where('storefront_customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->latest()
            ->first();
        if (! $address) {
            return null;
        }

        return [
            'line' => $address->line,
            'city' => $address->city,
            'label' => $address->label,
            'customerName' => $customer->name,
        ];
    }

    public function saveDefaultAddress(StorefrontCustomer $customer, string $line, ?string $city = null): StorefrontAddress
    {
        $address = StorefrontAddress::where('company_id', $customer->company_id)
            ->where('storefront_customer_id', $customer->id)
            ->where('is_default', true)
            ->first();

        if (! $address) {
            $address = new StorefrontAddress([
                'company_id' => $customer->company_id,
                'storefront_customer_id' => $customer->id,
                'is_default' => true,
            ]);
        }

        $address->line = $line;
        $address->city = $city;
        $address->save();

        return $address;
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
     * @param  array{customerName?: ?string, customerPhone?: ?string, customerEmail?: ?string, deliveryAddress?: ?string, fulfillmentType?: ?string, dineInTableCode?: ?string, orderNotes?: ?string, giftMessage?: ?string, tipAmount?: float|string|null, couponCode?: ?string}  $checkout
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
        $customerEmailRaw = trim((string) ($checkout['customerEmail'] ?? ''));
        $customerEmail = $customerEmailRaw !== '' ? $customerEmailRaw : null;
        $orderNotesRaw = trim((string) ($checkout['orderNotes'] ?? ''));
        $orderNotes = $orderNotesRaw !== '' ? $orderNotesRaw : null;
        $giftMessageRaw = trim((string) ($checkout['giftMessage'] ?? ''));
        $giftMessage = $giftMessageRaw !== '' ? $giftMessageRaw : null;
        $tipAmount = round(max(0, (float) ($checkout['tipAmount'] ?? 0)), 2);
        $couponCodeRaw = trim((string) ($checkout['couponCode'] ?? ''));
        $scheduledFor = null;
        if (! empty($checkout['scheduledFor'])) {
            try {
                $scheduledFor = \Illuminate\Support\Carbon::parse($checkout['scheduledFor']);
            } catch (\Throwable) {
                $scheduledFor = null;
            }
        }

        // Re-check stock at order time — items may have sold out since being added to cart.
        foreach ($summary['items'] as $item) {
            $lineProduct = Product::where('company_id', $company->id)->find((int) $item['productId']);
            if (! $lineProduct) {
                continue;
            }
            $lineVariant = $item['productVariantId']
                ? ProductVariant::where('product_id', $lineProduct->id)->find((int) $item['productVariantId'])
                : null;
            $this->assertStockAvailable($lineProduct, $lineVariant, (int) $item['quantity']);
        }

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

        $coupon = null;
        $discountTotal = 0.0;
        if ($couponCodeRaw !== '') {
            $coupon = $this->resolveCoupon($company, $couponCodeRaw, (float) $summary['subtotal']);
            if (! $coupon) {
                throw new \RuntimeException('This coupon code is invalid or does not apply.');
            }
            $discountTotal = $coupon->discountForSubtotal((float) $summary['subtotal']);
        }

        $orderNumber = 'ORD-'.strtoupper(Str::random(8));
        while (Order::where('order_number', $orderNumber)->exists()) {
            $orderNumber = 'ORD-'.strtoupper(Str::random(8));
        }

        $spamFlagged = $customerPhone !== '' && $this->isSpam($company, $customerPhone);

        $chatId = null;
        if ($customerPhone !== '') {
            $chat = $this->whatsappBridge->resolveOrCreateChat($company, $customerPhone, $customerName);
            $chatId = $chat?->id;
        }

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chatId,
            'order_number' => $orderNumber,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone ?: null,
            'customer_email' => $customerEmail,
            'delivery_address' => $deliveryAddress,
            'fulfillment_type' => $fulfillmentType,
            'dine_in_table_id' => $dineInTable?->id,
            'dine_in_table_name' => $dineInTable?->name,
            'subtotal' => $summary['subtotal'],
            'tax_total' => $summary['taxTotal'],
            'tax_breakdown' => $summary['taxBreakdown'] !== [] ? $summary['taxBreakdown'] : null,
            'delivery_fee' => $deliveryFee,
            'tip_amount' => $tipAmount,
            'discount_total' => $discountTotal,
            'coupon_code' => $coupon ? $coupon->code : null,
            'coupon_id' => $coupon?->id,
            'order_notes' => $orderNotes,
            'gift_message' => $giftMessage,
            'scheduled_for' => $scheduledFor,
            'total' => max(0, round($summary['total'] + $deliveryFee + $tipAmount - $discountTotal, 2)),
            'status' => 'pending',
            'payment_status' => 'pending',
            'source' => 'storefront',
            'spam_flagged' => $spamFlagged,
        ]);

        if ($coupon) {
            $coupon->increment('redeemed_count');
        }

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

            if ($product && $product->isBundle()) {
                $this->expandBundleLines($order, $product, $item['quantity']);
            }
        }

        $order->ensurePublicTokens();
        $this->clearCart($session);

        if ($customerPhone !== '') {
            $customer = $this->findOrCreateCustomer($company, $customerPhone, $customerName, $customerEmail ?: null);
            if ($customer && $deliveryAddress) {
                $this->saveDefaultAddress($customer, $deliveryAddress);
            }
            $this->whatsappBridge->notifyOrderPlaced($order->fresh(['company.settings', 'orderProducts', 'chat']));
        }

        return $order;
    }

    public function resolveCoupon(Company $company, string $code, ?float $subtotal = null): ?StorefrontCoupon
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $coupon = StorefrontCoupon::where('company_id', $company->id)
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->first();

        if (! $coupon || ! $coupon->isCurrentlyValid()) {
            return null;
        }

        if ($subtotal !== null && $coupon->min_order !== null && $subtotal < (float) $coupon->min_order) {
            return null;
        }

        return $coupon;
    }

    /**
     * @return array{
     *   subtotal: float,
     *   taxTotal: float,
     *   deliveryFee: float,
     *   discountTotal: float,
     *   tipAmount: float,
     *   total: float,
     *   couponCode: ?string,
     *   couponValid: bool
     * }
     */
    public function quoteCheckout(Company $company, StorefrontSession $session, array $input = []): array
    {
        $summary = $this->cartSummary($company, $session);
        $fulfillmentType = $input['fulfillmentType'] ?? $session->fulfillment_type ?? 'delivery';
        if (! in_array($fulfillmentType, ['delivery', 'pickup', 'dine_in'], true)) {
            $fulfillmentType = 'delivery';
        }
        $deliveryAddress = $fulfillmentType === 'delivery' ? ($input['deliveryAddress'] ?? null) : null;
        $deliveryFee = $this->deliveryFeeForCompany($company, (float) $summary['subtotal'], $fulfillmentType, $deliveryAddress);
        $tipAmount = max(0, round((float) ($input['tipAmount'] ?? 0), 2));
        $couponCode = strtoupper(trim((string) ($input['couponCode'] ?? $session->coupon_code ?? '')));
        $coupon = $couponCode !== '' ? $this->resolveCoupon($company, $couponCode) : null;
        $discountTotal = $coupon ? $coupon->discountForSubtotal((float) $summary['subtotal']) : 0.0;

        return [
            'subtotal' => (float) $summary['subtotal'],
            'taxTotal' => (float) $summary['taxTotal'],
            'deliveryFee' => $deliveryFee,
            'discountTotal' => $discountTotal,
            'tipAmount' => $tipAmount,
            'total' => round(max(0, $summary['total'] + $deliveryFee + $tipAmount - $discountTotal), 2),
            'couponCode' => $coupon?->code,
            'couponValid' => $couponCode === '' || ($coupon !== null && $discountTotal > 0),
        ];
    }

    public function trackOrder(Company $company, string $phone, string $orderNumber): ?Order
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        $orderNumber = trim($orderNumber);
        if ($phone === '' || $orderNumber === '') {
            return null;
        }

        $order = Order::where('company_id', $company->id)
            ->where('order_number', $orderNumber)
            ->with('orderProducts')
            ->first();

        if (! $order) {
            return null;
        }

        $orderPhone = preg_replace('/\D+/', '', (string) $order->customer_phone) ?? '';
        if ($orderPhone === '') {
            return null;
        }

        $phoneMatches = $orderPhone === $phone
            || str_ends_with($orderPhone, $phone)
            || str_ends_with($phone, $orderPhone);

        return $phoneMatches ? $order : null;
    }

    /** @return list<array<string, mixed>> */
    public function relatedProducts(Company $company, Product $product, int $limit = 4): array
    {
        $relatedIds = ProductRelationship::where('company_id', $company->id)
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('related_product_id')
            ->all();

        $related = [];
        if ($relatedIds !== []) {
            $related = Product::where('company_id', $company->id)
                ->where('status', 'active')
                ->whereIn('id', $relatedIds)
                ->with(['activeVariants', 'images'])
                ->get()
                ->map(fn (Product $p) => $this->serializeProduct($p))
                ->values()
                ->all();
        }

        if (count($related) >= $limit) {
            return array_slice($related, 0, $limit);
        }

        $fallback = Product::where('company_id', $company->id)
            ->where('status', 'active')
            ->where('id', '!=', $product->id)
            ->when($product->category, fn ($q) => $q->where('category', $product->category))
            ->with(['activeVariants', 'images'])
            ->orderBy('name')
            ->limit($limit - count($related))
            ->get()
            ->map(fn (Product $p) => $this->serializeProduct($p))
            ->values()
            ->all();

        return array_values(array_merge($related, $fallback));
    }

    public function recordEvent(Company $company, string $event, ?string $sessionToken = null, ?int $productId = null, array $meta = []): void
    {
        StorefrontEvent::create([
            'company_id' => $company->id,
            'session_token' => $sessionToken,
            'event' => $event,
            'product_id' => $productId,
            'meta' => $meta !== [] ? $meta : null,
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function analyticsSummary(Company $company, int $days = 30): array
    {
        $since = now()->subDays(max(1, $days));
        $rows = StorefrontEvent::where('company_id', $company->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('event, COUNT(*) as c')
            ->groupBy('event')
            ->pluck('c', 'event')
            ->all();

        return [
            'view_catalog' => (int) ($rows['view_catalog'] ?? 0),
            'view_product' => (int) ($rows['view_product'] ?? 0),
            'add_to_cart' => (int) ($rows['add_to_cart'] ?? 0),
            'begin_checkout' => (int) ($rows['begin_checkout'] ?? 0),
            'purchase' => (int) ($rows['purchase'] ?? 0),
        ];
    }

    public function whatsappUrl(?string $number, ?string $prefill = null): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number) ?? '';
        if ($digits === '') {
            return null;
        }
        $url = 'https://wa.me/'.$digits;
        if ($prefill) {
            $url .= '?text='.rawurlencode($prefill);
        }

        return $url;
    }

    /**
     * Feature 19: expand a `bundle` product's components into extra order lines so the
     * merchant can see (and fulfill) each child item. Pricing stays on the bundle line
     * itself — component lines are informational (zero-priced) to avoid double charging.
     */
    protected function expandBundleLines(Order $order, Product $bundle, int $bundleQuantity): void
    {
        $items = $bundle->relationLoaded('bundleItems')
            ? $bundle->bundleItems
            : $bundle->bundleItems()->with('child')->get();

        foreach ($items as $bundleItem) {
            $child = $bundleItem->child ?? Product::find($bundleItem->child_product_id);
            if (! $child) {
                continue;
            }

            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $child->id,
                'name' => $child->name.' (bundle item — '.$bundle->name.')',
                'quantity' => max(1, (int) $bundleItem->quantity) * max(1, $bundleQuantity),
                'price' => 0,
                'tax_amount' => 0,
                'tax_inclusive' => false,
                'line_subtotal' => 0,
                'fulfillment_data' => array_merge($child->fulfillmentSnapshot(), [
                    'bundleComponentOf' => $bundle->name,
                ]),
            ]);
        }
    }
}
