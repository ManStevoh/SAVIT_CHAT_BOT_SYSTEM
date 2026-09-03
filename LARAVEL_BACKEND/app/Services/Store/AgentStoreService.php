<?php

namespace App\Services\Store;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\AI\KnowledgeChunkService;
use App\Services\Logs\LogDataScrubber;
use Illuminate\Support\Facades\DB;
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

        $companies = $query->orderBy('name')->get();

        return $companies->map(fn (Company $c) => [
            'id'             => $c->id,
            'name'           => $c->name,
            'store_slug'     => $c->store_slug,
            'status'         => $c->status,
            'email'          => $c->email,
            'products_count' => $c->products_count,
        ])->all();
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
            'status'           => $p->status,
            'product_type'     => $p->product_type,
            'image'            => $p->image,
            'description'      => $p->description,
        ];
    }
}
