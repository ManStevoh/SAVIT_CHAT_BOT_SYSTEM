<?php

namespace App\Services\Store;

use App\Models\Company;
use App\Models\CustomerMemory;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Agent\AgentCommerceProvisioningService;
use App\Services\AI\KnowledgeChunkService;
use App\Services\Logs\LogDataScrubber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class AgentStoreService
{
    /**
     * List all companies/stores with active product counts.
     */
    public function listStores(?string $search = null): array
    {
        $query = Company::query()
            ->select(['id', 'name', 'store_slug', 'status', 'email', 'created_at'])
            ->withCount('products');

        if (! empty($search)) {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('store_slug', 'like', $term);
            });
        }

        $companies = $query->with('settings')->orderBy('name')->get();

        return $companies->map(fn (Company $c) => [
            'id'              => $c->id,
            'name'            => $c->name,
            'store_slug'      => $c->store_slug,
            'status'          => $c->status,
            'email'           => $c->email,
            'currency'        => $c->settings?->displayCurrencyCode() ?? 'KES',
            'currency_symbol' => $c->settings?->currency_symbol ?? 'KSh',
            'products_count'  => $c->products_count,
        ])->all();
    }

    /**
     * Update store settings (e.g. display_currency, currency_symbol, store_name).
     */
    public function updateStoreSettings(Company $company, array $data): array
    {
        $company->loadMissing('settings');
        $settings = $company->settings;
        if (! $settings) {
            $settings = $company->settings()->create([
                'display_currency' => 'KES',
                'currency_symbol'  => 'KSh',
            ]);
        }

        $fields = [];
        if (isset($data['display_currency']) || isset($data['currency'])) {
            $raw = (string) ($data['display_currency'] ?? $data['currency']);
            $code = strtoupper(preg_replace('/[^A-Za-z]/', '', $raw) ?? '');
            $fields['display_currency'] = strlen($code) >= 3 ? substr($code, 0, 3) : 'KES';
        }

        if (array_key_exists('currency_symbol', $data) || array_key_exists('symbol', $data)) {
            $symbol = $data['currency_symbol'] ?? $data['symbol'];
            $fields['currency_symbol'] = is_string($symbol) && trim($symbol) !== ''
                ? mb_substr(trim($symbol), 0, 16)
                : null;
        }

        if (array_key_exists('order_payment_manual_instructions', $data) || array_key_exists('manual_instructions', $data) || array_key_exists('payment_instructions', $data)) {
            $instr = $data['order_payment_manual_instructions'] ?? $data['manual_instructions'] ?? $data['payment_instructions'];
            $fields['order_payment_manual_instructions'] = is_string($instr) && trim($instr) !== '' ? trim($instr) : null;
        }

        if (array_key_exists('orders_collect_payment_enabled', $data) || array_key_exists('collect_payment', $data)) {
            $fields['orders_collect_payment_enabled'] = (bool) ($data['orders_collect_payment_enabled'] ?? $data['collect_payment']);
        }

        if (array_key_exists('orders_accept_mpesa', $data)) {
            $fields['orders_accept_mpesa'] = (bool) $data['orders_accept_mpesa'];
        }

        if (array_key_exists('orders_accept_cod', $data)) {
            $fields['orders_accept_cod'] = (bool) $data['orders_accept_cod'];
        }

        if (! empty($fields)) {
            $settings->update($fields);
        }

        $companyFields = [];
        if (! empty($data['store_name']) || ! empty($data['name'])) {
            $companyFields['name'] = trim((string) ($data['store_name'] ?? $data['name']));
        }
        if (array_key_exists('billing_model', $data) || array_key_exists('billingModel', $data)) {
            $companyFields['billing_model'] = $data['billing_model'] ?? $data['billingModel'];
        }
        if (array_key_exists('commission_rate', $data) || array_key_exists('commissionRate', $data)) {
            $val = $data['commission_rate'] ?? $data['commissionRate'];
            $companyFields['commission_rate'] = ($val !== null && $val !== '') ? (float) $val : null;
        }
        if (array_key_exists('commission_basis', $data) || array_key_exists('commissionBasis', $data)) {
            $companyFields['commission_basis'] = $data['commission_basis'] ?? $data['commissionBasis'];
        }
        if (array_key_exists('waive_subscription_fee', $data) || array_key_exists('waiveSubscriptionFee', $data)) {
            $companyFields['waive_subscription_fee'] = (bool) ($data['waive_subscription_fee'] ?? $data['waiveSubscriptionFee']);
        }
        if (array_key_exists('commission_invoice_threshold', $data) || array_key_exists('commissionInvoiceThreshold', $data)) {
            $val = $data['commission_invoice_threshold'] ?? $data['commissionInvoiceThreshold'];
            $companyFields['commission_invoice_threshold'] = ($val !== null && $val !== '') ? (float) $val : null;
        }
        if (array_key_exists('storefront_sections', $data)) {
            $companyFields['storefront_sections'] = $data['storefront_sections'];
        }
        if (array_key_exists('storefront_theme', $data)) {
            $companyFields['storefront_theme'] = $data['storefront_theme'];
        }

        if (! empty($companyFields)) {
            $company->update($companyFields);
        }

        $freshCompany = $company->fresh();

        $this->recordAudit('agent_store_settings_updated', $company->id, [
            'settings_updates' => array_keys($fields),
            'company_updates'  => array_keys($companyFields),
        ]);

        return [
            'success'                    => true,
            'company_id'                 => $company->id,
            'company_name'               => $freshCompany->name,
            'store_slug'                 => $freshCompany->store_slug,
            'billing_model'              => $freshCompany->billing_model,
            'commission_rate'            => $freshCompany->commission_rate !== null ? (float) $freshCompany->commission_rate : null,
            'commission_basis'           => $freshCompany->commission_basis,
            'waive_subscription_fee'     => (bool) ($freshCompany->waive_subscription_fee ?? true),
            'currency'                   => $settings->fresh()->displayCurrencyCode(),
            'currency_symbol'            => $settings->fresh()->currency_symbol,
            'message'                    => "Store '{$freshCompany->name}' settings updated successfully.",
        ];
    }

    /**
     * Resolve a company by ID, store_slug, or exact name.
     */
    public function resolveCompany(int|string|null $identifier): ?Company
    {
        if (empty($identifier)) {
            return null;
        }

        if (is_numeric($identifier)) {
            return Company::find((int) $identifier);
        }

        $str = trim((string) $identifier);

        return Company::where('store_slug', $str)
            ->orWhere('name', $str)
            ->first();
    }

    /**
     * List products for a specific company with optional search and filters.
     */
    public function listProducts(Company $company, array $filters = []): array
    {
        $query = Product::where('company_id', $company->id);

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('description', 'like', $term)
                  ->orWhere('category', 'like', $term);
            });
        }

        $limit = max(1, min(200, (int) ($filters['limit'] ?? 50)));

        $products = $query->orderBy('name')->limit($limit)->get();

        return $products->map(fn (Product $p) => $this->productToSummary($p))->all();
    }

    /**
     * Create a single product for a company.
     */
    public function createProduct(Company $company, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Product name is required.');
        }

        if (! isset($data['price']) || ! is_numeric($data['price']) || (float) $data['price'] < 0) {
            throw new InvalidArgumentException('A valid non-negative product price is required.');
        }

        $price = round((float) $data['price'], 2);
        $compareAtPrice = isset($data['compare_at_price']) && is_numeric($data['compare_at_price'])
            ? round((float) $data['compare_at_price'], 2)
            : (isset($data['compareAtPrice']) && is_numeric($data['compareAtPrice']) ? round((float) $data['compareAtPrice'], 2) : null);

        $stock = isset($data['stock']) ? max(0, (int) $data['stock']) : 0;
        $category = ! empty($data['category']) ? trim((string) $data['category']) : null;
        $description = ! empty($data['description']) ? trim((string) $data['description']) : null;
        $status = in_array(strtolower((string) ($data['status'] ?? 'active')), ['active', 'inactive', 'draft'], true)
            ? strtolower((string) ($data['status'] ?? 'active'))
            : 'active';

        $productType = in_array(strtolower((string) ($data['product_type'] ?? $data['productType'] ?? 'physical')), ['physical', 'digital', 'service'], true)
            ? strtolower((string) ($data['product_type'] ?? $data['productType'] ?? 'physical'))
            : 'physical';

        $fulfillmentType = in_array(strtolower((string) ($data['fulfillment_type'] ?? $data['fulfillmentType'] ?? 'manual')), ['shipping', 'download', 'link', 'booking', 'manual'], true)
            ? strtolower((string) ($data['fulfillment_type'] ?? $data['fulfillmentType'] ?? 'manual'))
            : 'manual';

        $trackInventory = isset($data['track_inventory'])
            ? (bool) $data['track_inventory']
            : (isset($data['trackInventory']) ? (bool) $data['trackInventory'] : true);

        $slug = $this->generateUniqueSlug($company->id, $data['slug'] ?? $name);

        $imagePath = $this->resolveAndStoreImage($company->id, $data['image_url'] ?? $data['image'] ?? null);

        $product = Product::create([
            'company_id'               => $company->id,
            'name'                     => $name,
            'slug'                     => $slug,
            'price'                    => $price,
            'compare_at_price'         => $compareAtPrice,
            'category'                 => $category,
            'description'              => $description,
            'stock'                    => $stock,
            'status'                   => $status,
            'product_type'             => $productType,
            'fulfillment_type'         => $fulfillmentType,
            'track_inventory'          => $trackInventory,
            'image'                    => $imagePath,
            'requires_delivery_address'=> (bool) ($data['requires_delivery_address'] ?? $data['requiresDeliveryAddress'] ?? ($productType === 'physical')),
        ]);

        if (! empty($imagePath)) {
            try {
                ProductImage::create([
                    'company_id' => $company->id,
                    'product_id' => $product->id,
                    'path'       => $imagePath,
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
            } catch (Throwable) {}
        }

        $this->syncEmbeddings($product);

        $this->recordAudit('agent_product_created', $company->id, [
            'product_id' => $product->id,
            'name'       => $product->name,
            'price'      => $product->price,
        ]);

        return [
            'success' => true,
            'message' => "Product '{$product->name}' created successfully.",
            'product' => $this->productToSummary($product),
        ];
    }

    /**
     * Update an existing product identified by ID, slug, or name.
     */
    public function updateProduct(Company $company, int|string $productIdentifier, array $data): array
    {
        $product = $this->resolveProduct($company, $productIdentifier);
        if (! $product) {
            throw new InvalidArgumentException("Product '{$productIdentifier}' not found in store '{$company->name}'.");
        }

        $fields = [];

        if (isset($data['name']) && trim((string) $data['name']) !== '') {
            $fields['name'] = trim((string) $data['name']);
        }

        if (isset($data['price']) && is_numeric($data['price'])) {
            $fields['price'] = round((float) $data['price'], 2);
        }

        if (array_key_exists('compare_at_price', $data) || array_key_exists('compareAtPrice', $data)) {
            $cap = $data['compare_at_price'] ?? $data['compareAtPrice'] ?? null;
            $fields['compare_at_price'] = (is_numeric($cap) && (float) $cap >= 0) ? round((float) $cap, 2) : null;
        }

        if (array_key_exists('stock', $data) && is_numeric($data['stock'])) {
            $fields['stock'] = max(0, (int) $data['stock']);
        }

        if (array_key_exists('category', $data)) {
            $fields['category'] = ! empty($data['category']) ? trim((string) $data['category']) : null;
        }

        if (array_key_exists('description', $data)) {
            $fields['description'] = ! empty($data['description']) ? trim((string) $data['description']) : null;
        }

        if (! empty($data['status']) && in_array(strtolower((string) $data['status']), ['active', 'inactive', 'draft'], true)) {
            $fields['status'] = strtolower((string) $data['status']);
        }

        if (! empty($data['image_url'])) {
            $newImage = $this->resolveAndStoreImage($company->id, $data['image_url']);
            if ($newImage) {
                $fields['image'] = $newImage;
            }
        }

        if (array_key_exists('requires_delivery_address', $data) || array_key_exists('requiresDeliveryAddress', $data)) {
            $val = $data['requires_delivery_address'] ?? $data['requiresDeliveryAddress'];
            $fields['requires_delivery_address'] = (bool) $val;
        }

        if (! empty($fields)) {
            $product->update($fields);
            $this->syncEmbeddings($product);
        }

        $this->recordAudit('agent_product_updated', $company->id, [
            'product_id' => $product->id,
            'updates'    => array_keys($fields),
        ]);

        return [
            'success' => true,
            'message' => "Product '{$product->name}' updated successfully.",
            'product' => $this->productToSummary($product->fresh()),
        ];
    }

    /**
     * Remove or archive a product.
     */
    public function deleteProduct(Company $company, int|string $productIdentifier, bool $force = false): array
    {
        $product = $this->resolveProduct($company, $productIdentifier);
        if (! $product) {
            throw new InvalidArgumentException("Product '{$productIdentifier}' not found in store '{$company->name}'.");
        }

        $name = $product->name;
        $id = $product->id;

        if ($force) {
            $product->delete();
            $action = 'deleted';
        } else {
            $product->update(['status' => 'inactive']);
            $action = 'archived';
        }

        $this->recordAudit("agent_product_{$action}", $company->id, [
            'product_id' => $id,
            'name'       => $name,
            'forced'     => $force,
        ]);

        return [
            'success' => true,
            'action'  => $action,
            'message' => "Product '{$name}' (ID: {$id}) {$action} successfully.",
        ];
    }

    /**
     * Batch import or add multiple products in a single database transaction.
     */
    public function bulkImport(Company $company, array $items): array
    {
        if (empty($items)) {
            throw new InvalidArgumentException('Items array cannot be empty for bulk import.');
        }

        $created = [];
        $errors = [];

        DB::transaction(function () use ($company, $items, &$created, &$errors) {
            foreach ($items as $idx => $item) {
                try {
                    $res = $this->createProduct($company, (array) $item);
                    $created[] = $res['product'];
                } catch (Throwable $e) {
                    $errors[] = [
                        'index'   => $idx,
                        'item'    => $item['name'] ?? "Item #{$idx}",
                        'message' => $e->getMessage(),
                    ];
                }
            }
        });

        $this->recordAudit('agent_bulk_import', $company->id, [
            'total'   => count($items),
            'created' => count($created),
            'errors'  => count($errors),
        ]);

        return [
            'success'       => count($errors) === 0,
            'company_id'    => $company->id,
            'company_name'  => $company->name,
            'created_count' => count($created),
            'error_count'   => count($errors),
            'products'      => $created,
            'errors'        => $errors,
        ];
    }

    /**
     * Resolve a product by ID, slug, or case-insensitive name.
     */
    public function resolveProduct(Company $company, int|string $identifier): ?Product
    {
        if (is_numeric($identifier)) {
            return Product::where('company_id', $company->id)
                ->where('id', (int) $identifier)
                ->first();
        }

        $str = trim((string) $identifier);

        return Product::where('company_id', $company->id)
            ->where(function ($q) use ($str) {
                $q->where('slug', $str)
                  ->orWhere('name', $str);
            })
            ->first();
    }

    /**
     * Generate unique slug for the company.
     */
    private function generateUniqueSlug(int $companyId, string $source): string
    {
        $base = Str::slug($source);
        if ($base === '') {
            $base = 'product';
        }

        $slug = $base;
        $counter = 1;

        while (Product::where('company_id', $companyId)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$counter);
        }

        return $slug;
    }

    /**
     * Download or resolve an image path.
     */
    private function resolveAndStoreImage(int $companyId, ?string $imageSource): ?string
    {
        if (empty($imageSource)) {
            return null;
        }

        $imageSource = trim($imageSource);

        // If it is an external URL, download and save locally
        if (filter_var($imageSource, FILTER_VALIDATE_URL)) {
            try {
                $response = Http::timeout(10)->get($imageSource);
                if ($response->successful()) {
                    $ext = 'jpg';
                    $contentType = $response->header('Content-Type');
                    if (str_contains((string) $contentType, 'png')) $ext = 'png';
                    elseif (str_contains((string) $contentType, 'webp')) $ext = 'webp';
                    elseif (str_contains((string) $contentType, 'gif')) $ext = 'gif';

                    $filename = 'products/' . $companyId . '/' . Str::random(32) . '.' . $ext;
                    Storage::disk('public')->put($filename, $response->body());

                    return $filename;
                }
            } catch (Throwable $e) {
                Log::warning("AgentStoreService: Failed to download product image from {$imageSource}: " . $e->getMessage());
            }
        }

        // If it is a base64 data URI or raw base64 payload
        if (str_starts_with($imageSource, 'data:image/')) {
            try {
                [$meta, $rawBase64] = explode(',', $imageSource, 2);
                $ext = 'png';
                if (str_contains($meta, 'jpeg') || str_contains($meta, 'jpg')) $ext = 'jpg';
                elseif (str_contains($meta, 'webp')) $ext = 'webp';
                elseif (str_contains($meta, 'gif')) $ext = 'gif';

                $decoded = base64_decode($rawBase64);
                if ($decoded !== false && strlen($decoded) > 0) {
                    $filename = 'products/' . $companyId . '/' . Str::random(32) . '.' . $ext;
                    Storage::disk('public')->put($filename, $decoded);

                    return $filename;
                }
            } catch (Throwable $e) {
                Log::warning("AgentStoreService: Failed to decode base64 product image: " . $e->getMessage());
            }
        }

        // Relative path or local storage key
        return $imageSource;
    }

    /**
     * Synchronize vector AI knowledge chunk for the product.
     */
    private function syncEmbeddings(Product $product): void
    {
        if ($product->status !== 'active') {
            return;
        }

        try {
            if (app()->bound(KnowledgeChunkService::class)) {
                app(KnowledgeChunkService::class)->syncProduct($product);
            }
        } catch (Throwable $e) {
            // Non-blocking
            Log::info("AgentStoreService: Knowledge chunk sync skipped or deferred: " . $e->getMessage());
        }
    }

    /**
     * Record an audit event.
     */
    private function recordAudit(string $action, int $companyId, array $details): void
    {
        try {
            DB::table('audit_events')->insert([
                'company_id'   => $companyId,
                'event_name'   => $action,
                'details'      => json_encode(LogDataScrubber::scrubArray(array_merge($details, [
                    'ip'         => request()?->ip() ?? '127.0.0.1',
                    'timestamp'  => now()->toIso8601String(),
                    'user_agent' => request()?->userAgent() ?? 'Agent/StoreGateway',
                ])), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at'   => now(),
            ]);
        } catch (Throwable) {
            // Table or column may vary across deployments
        }
    }

    /**
     * Format a product model into a clean LLM summary array.
     */
    public function productToSummary(Product $p): array
    {
        return [
            'id'               => $p->id,
            'company_id'       => $p->company_id,
            'name'             => $p->name,
            'slug'             => $p->slug,
            'price'            => (float) $p->price,
            'compare_at_price' => $p->compare_at_price ? (float) $p->compare_at_price : null,
            'stock'            => (int) $p->stock,
            'category'         => $p->category,
            'status'                    => $p->status,
            'product_type'              => $p->product_type,
            'requires_delivery_address' => (bool) ($p->requires_delivery_address ?? ($p->product_type === 'physical')),
            'requiresDeliveryAddress'   => (bool) ($p->requires_delivery_address ?? ($p->product_type === 'physical')),
            'image'                     => $p->image,
            'description'               => $p->description,
        ];
    }

    /**
     * List customer memories for a store, optionally filtered by customer phone.
     */
    public function listMemories(Company $company, ?string $phone = null): array
    {
        $query = CustomerMemory::query()->where('company_id', $company->id);
        if (! empty($phone)) {
            $normalized = preg_replace('/\D+/', '', $phone) ?? $phone;
            $query->where('customer_phone', $normalized);
        }

        $records = $query->orderByDesc('updated_at')->limit(100)->get();

        return [
            'success'      => true,
            'company_id'   => $company->id,
            'company_name' => $company->name,
            'count'        => $records->count(),
            'memories'     => $records->map(fn (CustomerMemory $m) => [
                'id'             => $m->id,
                'customer_phone' => $m->customer_phone,
                'memory_key'     => $m->memory_key,
                'memory_value'   => $m->memory_value,
                'category'       => $m->category,
                'source'         => $m->source,
                'confidence'     => $m->confidence,
                'updated_at'     => $m->updated_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * Clear customer memories for a store, optionally filtered by customer phone or key.
     */
    public function clearMemories(Company $company, ?string $phone = null, ?string $key = null): array
    {
        $query = CustomerMemory::query()->where('company_id', $company->id);
        if (! empty($phone)) {
            $normalized = preg_replace('/\D+/', '', $phone) ?? $phone;
            $query->where('customer_phone', $normalized);
        }
        if (! empty($key)) {
            $query->where('memory_key', trim($key));
        }

        $deleted = $query->delete();

        $this->recordAudit('agent_customer_memories_cleared', $company->id, [
            'phone'         => $phone,
            'key'           => $key,
            'deleted_count' => $deleted,
        ]);

        return [
            'success'       => true,
            'company_id'    => $company->id,
            'company_name'  => $company->name,
            'deleted_count' => $deleted,
            'message'       => "Successfully cleared {$deleted} customer memories.",
        ];
    }

    /**
     * Clone an existing store (catalog, settings, etc.) into a new or existing tenant company,
     * and seed a pre-verified owner account without requiring email/OTP verification.
     */
    public function cloneStore(array $data): array
    {
        $sourceId = $data['source_store'] ?? $data['source_company_id'] ?? $data['source'] ?? null;
        if (empty($sourceId)) {
            throw new InvalidArgumentException('Source store identifier (source_store) is required.');
        }

        $sourceCompany = $this->resolveCompany($sourceId);
        if (! $sourceCompany) {
            throw new InvalidArgumentException("Source store '{$sourceId}' could not be found.");
        }

        $userData = (array) ($data['user'] ?? []);
        $storeData = (array) ($data['store'] ?? $data['company'] ?? []);

        $userEmail = strtolower(trim((string) ($userData['email'] ?? $data['user_email'] ?? $data['email'] ?? '')));
        if ($userEmail === '' || ! filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid user email address is required.');
        }

        $userName = trim((string) ($userData['name'] ?? $data['user_name'] ?? $data['name'] ?? 'Store Owner'));
        $userPhone = trim((string) ($userData['phone'] ?? $data['user_phone'] ?? $data['phone'] ?? '+254700000000'));
        $password = (string) ($userData['password'] ?? $data['password'] ?? 'Jostinah@2026!');

        $companyName = trim((string) ($storeData['name'] ?? $data['company_name'] ?? $data['store_name'] ?? ($userName . "'s Store")));
        $storeSlug = trim((string) ($storeData['store_slug'] ?? $storeData['slug'] ?? $data['store_slug'] ?? ''));

        if ($storeSlug === '') {
            $storeSlug = Str::slug($companyName);
        } else {
            $storeSlug = Str::slug($storeSlug);
        }

        return DB::transaction(function () use (
            $sourceCompany,
            $userEmail,
            $userName,
            $userPhone,
            $password,
            $companyName,
            $storeSlug,
            $data
        ) {
            // Find existing company by email or create new
            $targetCompany = Company::where('email', $userEmail)->first();
            if (! $targetCompany) {
                // Ensure unique slug
                $uniqueSlug = $storeSlug;
                $counter = 1;
                while (Company::where('store_slug', $uniqueSlug)->exists()) {
                    $uniqueSlug = "{$storeSlug}-{$counter}";
                    $counter++;
                }

                $targetCompany = Company::create([
                    'name'                => $companyName,
                    'store_slug'          => $uniqueSlug,
                    'email'               => $userEmail,
                    'phone'               => $userPhone,
                    'status'              => 'active',
                    'storefront_enabled'  => true,
                    'link_in_bio_enabled' => false,
                ]);
            } else {
                if (empty($targetCompany->store_slug)) {
                    $uniqueSlug = $storeSlug;
                    $counter = 1;
                    while (Company::where('store_slug', $uniqueSlug)->where('id', '!=', $targetCompany->id)->exists()) {
                        $uniqueSlug = "{$storeSlug}-{$counter}";
                        $counter++;
                    }
                    $targetCompany->update(['store_slug' => $uniqueSlug, 'storefront_enabled' => true]);
                }
            }

            // Sync settings from source store
            $sourceCompany->loadMissing('settings');
            $sourceSettings = $sourceCompany->settings;
            $displayCurrency = $sourceSettings?->displayCurrencyCode() ?? 'KES';
            $currencySymbol = $sourceSettings?->currency_symbol ?? 'KSh';

            $targetSettings = $targetCompany->settings()->firstOrCreate(
                ['company_id' => $targetCompany->id],
                [
                    'display_currency'       => $displayCurrency,
                    'currency_symbol'        => $currencySymbol,
                    'agent_commerce_enabled' => true,
                    'auto_reply_enabled'     => true,
                ]
            );
            $targetSettings->update([
                'display_currency'       => $displayCurrency,
                'currency_symbol'        => $currencySymbol,
                'agent_commerce_enabled' => true,
                'auto_reply_enabled'     => true,
            ]);

            // Create or update User as company owner, pre-verified
            $user = User::where('email', $userEmail)->first();
            if (! $user) {
                $user = User::create([
                    'name'              => $userName,
                    'email'             => $userEmail,
                    'phone'             => $userPhone,
                    'password'          => Hash::make($password),
                    'role'              => 'company_owner',
                    'company_id'        => $targetCompany->id,
                    'status'            => 'active',
                    'terms_accepted_at' => now(),
                ]);
            } else {
                $user->update([
                    'name'       => $userName,
                    'company_id' => $targetCompany->id,
                    'role'       => 'company_owner',
                    'status'     => 'active',
                    'password'   => Hash::make($password),
                ]);
            }
            $user->markEmailAsVerified();
            $user->save();

            // Set Starter plan if available and sync entitlements
            try {
                if (class_exists(Plan::class)) {
                    $plan = Plan::where('is_default', true)->first() ?? Plan::first();
                    if ($plan && empty($targetCompany->plan)) {
                        $targetCompany->update(['plan' => $plan->slug]);
                    }
                }
                if (class_exists(AgentCommerceProvisioningService::class)) {
                    app(AgentCommerceProvisioningService::class)->syncForCompany($targetCompany);
                }
            } catch (Throwable) {}

            // Clone products from source store
            $sourceProducts = $sourceCompany->products()
                ->whereIn('status', ['active', 'inactive', 'draft'])
                ->get();

            $clonedProducts = [];
            foreach ($sourceProducts as $sourceProd) {
                // Check if product with same name already exists in target
                $existing = $targetCompany->products()->where('name', $sourceProd->name)->first();
                if ($existing) {
                    $clonedProducts[] = $this->productToSummary($existing);
                    continue;
                }

                $newSlug = $this->generateUniqueSlug($targetCompany->id, $sourceProd->slug ?: $sourceProd->name);

                $product = Product::create([
                    'company_id'                => $targetCompany->id,
                    'name'                      => $sourceProd->name,
                    'slug'                      => $newSlug,
                    'price'                     => $sourceProd->price,
                    'compare_at_price'          => $sourceProd->compare_at_price,
                    'category'                  => $sourceProd->category,
                    'description'               => $sourceProd->description,
                    'stock'                     => $sourceProd->stock,
                    'status'                    => $sourceProd->status,
                    'product_type'              => $sourceProd->product_type,
                    'fulfillment_type'          => $sourceProd->fulfillment_type,
                    'track_inventory'           => $sourceProd->track_inventory,
                    'requires_delivery_address' => $sourceProd->requires_delivery_address,
                    'image'                     => $sourceProd->image,
                    'digital_file_path'         => $sourceProd->digital_file_path,
                    'digital_file_name'         => $sourceProd->digital_file_name,
                    'digital_file_mime'         => $sourceProd->digital_file_mime,
                    'digital_file_size'         => $sourceProd->digital_file_size,
                    'license_key_mode'          => $sourceProd->license_key_mode,
                    'access_url'                => $sourceProd->access_url,
                    'fulfillment_instructions'  => $sourceProd->fulfillment_instructions,
                ]);

                if (! empty($sourceProd->image)) {
                    try {
                        ProductImage::create([
                            'company_id' => $targetCompany->id,
                            'product_id' => $product->id,
                            'path'       => $sourceProd->image,
                            'is_primary' => true,
                            'sort_order' => 0,
                        ]);
                    } catch (Throwable) {}
                }

                $this->syncEmbeddings($product);
                $clonedProducts[] = $this->productToSummary($product);
            }

            $this->recordAudit('agent_store_cloned', $targetCompany->id, [
                'source_company_id' => $sourceCompany->id,
                'cloned_count'      => count($clonedProducts),
                'user_email'        => $userEmail,
            ]);

            return [
                'success'               => true,
                'message'               => "Store '{$targetCompany->name}' and account for {$userEmail} created successfully.",
                'company_id'            => $targetCompany->id,
                'company_name'          => $targetCompany->name,
                'store_slug'            => $targetCompany->store_slug,
                'storefront_url'        => rtrim(config('app.url', 'https://relayiq.app'), '/') . '/s/' . $targetCompany->store_slug,
                'user'                  => [
                    'id'                 => $user->id,
                    'name'               => $user->name,
                    'email'              => $user->email,
                    'phone'              => $user->phone,
                    'role'               => $user->role,
                    'email_verified'     => true,
                    'temporary_password' => $password,
                ],
                'products_cloned_count' => count($clonedProducts),
                'products'              => $clonedProducts,
            ];
        });
    }
}
