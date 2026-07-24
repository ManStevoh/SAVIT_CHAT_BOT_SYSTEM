<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductLicenseKey;
use App\Models\ProductVariant;
use App\Services\DigitalAccessService;
use App\Services\Platform\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    private const PRODUCT_TYPES = ['physical', 'digital', 'service'];

    private const FULFILLMENT_TYPES = ['shipping', 'download', 'link', 'booking', 'manual'];

    private const LICENSE_KEY_MODES = ['none', 'auto', 'pool'];

    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $query = Product::where('company_id', $companyId);

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $products = $query
            ->with($this->productRelations())
            ->orderBy('name')
            ->get();
        $data = $products->map(fn (Product $p) => $this->productToArray($p));

        return response()->json($data->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:255',
            'productType' => 'nullable|in:physical,digital,service',
            'fulfillmentType' => 'nullable|in:shipping,download,link,booking,manual',
            'trackInventory' => 'sometimes|boolean',
            'requiresDeliveryAddress' => 'sometimes|boolean',
            'accessUrl' => 'nullable|url|max:2048',
            'serviceBookingUrl' => 'nullable|url|max:2048',
            'fulfillmentInstructions' => 'nullable|string',
            'licenseKeyMode' => 'nullable|in:none,auto,pool',
            'licenseKeyPrefix' => 'nullable|string|max:32',
            'accessExpiresDays' => 'nullable|integer|min:1|max:3650',
            'maxDownloads' => 'nullable|integer|min:1',
            'bookable' => 'sometimes|boolean',
            'bookingDurationMinutes' => 'nullable|integer|min:5|max:480',
            'licenseKeys' => 'nullable|string',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|max:5120',
            'digitalFile' => 'nullable|file|max:20480|mimetypes:application/pdf,application/epub+zip,text/plain,text/csv,application/zip,application/x-zip-compressed,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
        $productType = $validated['productType'] ?? 'physical';
        if ($response = $this->catalogTypeEntitlementResponse((int) $companyId, $productType)) {
            return $response;
        }
        if (($validated['bookable'] ?? false)
            && ($response = $this->bookingEntitlementResponse((int) $companyId))) {
            return $response;
        }

        $payload = $this->normalizeProductPayload($validated, $request);
        $payload['company_id'] = $companyId;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products/'.$companyId, 'public');
            $payload['image'] = $path;
        }

        if ($request->hasFile('digitalFile')) {
            $payload = array_merge($payload, $this->storeDigitalFile($request, $companyId));
        }

        $product = Product::create($payload);
        if (! empty($payload['image'])) {
            $this->createImageRecord($product, null, $payload['image'], true, 0, null);
        }

        $this->importLicenseKeysFromRequest($product, $validated['licenseKeys'] ?? null);

        $product->load($this->productRelations());
        $this->syncProductEmbeddings($product);

        return response()->json([
            'success' => true,
            'product' => $this->productToArray($product),
            'message' => 'Product created successfully',
        ]);
    }

    public function storeVariant(Request $request, Product $product): JsonResponse
    {
        if ($product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:500',
            'price' => 'required|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
            'attributes' => 'nullable|array',
            'sortOrder' => 'sometimes|integer|min:0',
            'image' => 'nullable|image|max:5120',
            'isPrimaryImage' => 'sometimes|boolean',
        ]);

        $variant = $product->variants()->create([
            'label' => $validated['label'],
            'price' => $validated['price'],
            'stock' => (int) ($validated['stock'] ?? 0),
            'status' => $validated['status'] ?? 'active',
            'attributes' => $validated['attributes'] ?? null,
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products/'.$product->company_id.'/variants', 'public');
            $this->createImageRecord(
                $product,
                $variant,
                $path,
                (bool) ($validated['isPrimaryImage'] ?? true),
                0,
                $validated['label']
            );
        }

        return response()->json([
            'success' => true,
            'variant' => $this->variantToArray($variant->fresh()->load('images')),
            'message' => 'Variant created',
        ], 201);
    }

    public function updateVariant(Request $request, ProductVariant $productVariant): JsonResponse
    {
        $product = $productVariant->product;
        if (! $product || $product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'label' => 'sometimes|string|max:500',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
            'attributes' => 'nullable|array',
            'sortOrder' => 'sometimes|integer|min:0',
            'image' => 'nullable|image|max:5120',
            'isPrimaryImage' => 'sometimes|boolean',
        ]);

        $updates = [];
        if (array_key_exists('label', $validated)) {
            $updates['label'] = $validated['label'];
        }
        if (array_key_exists('price', $validated)) {
            $updates['price'] = $validated['price'];
        }
        if (array_key_exists('stock', $validated)) {
            $updates['stock'] = $validated['stock'];
        }
        if (array_key_exists('status', $validated)) {
            $updates['status'] = $validated['status'];
        }
        if (array_key_exists('attributes', $validated)) {
            $updates['attributes'] = $validated['attributes'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $updates['sort_order'] = $validated['sortOrder'];
        }
        $productVariant->update($updates);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products/'.$product->company_id.'/variants', 'public');
            $this->createImageRecord(
                $product,
                $productVariant,
                $path,
                (bool) ($validated['isPrimaryImage'] ?? true),
                0,
                $productVariant->label
            );
        }

        return response()->json([
            'success' => true,
            'variant' => $this->variantToArray($productVariant->fresh()->load('images')),
            'message' => 'Variant updated',
        ]);
    }

    public function destroyVariant(Request $request, ProductVariant $productVariant): JsonResponse
    {
        $product = $productVariant->product;
        if (! $product || $product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }
        $productVariant->delete();

        return response()->json(['success' => true, 'message' => 'Variant deleted']);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        if ($product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'category' => 'nullable|string|max:255',
            'productType' => 'sometimes|in:physical,digital,service',
            'fulfillmentType' => 'sometimes|in:shipping,download,link,booking,manual',
            'trackInventory' => 'sometimes|boolean',
            'requiresDeliveryAddress' => 'sometimes|boolean',
            'accessUrl' => 'nullable|url|max:2048',
            'serviceBookingUrl' => 'nullable|url|max:2048',
            'fulfillmentInstructions' => 'nullable|string',
            'licenseKeyMode' => 'nullable|in:none,auto,pool',
            'licenseKeyPrefix' => 'nullable|string|max:32',
            'accessExpiresDays' => 'nullable|integer|min:1|max:3650',
            'maxDownloads' => 'nullable|integer|min:1',
            'bookable' => 'sometimes|boolean',
            'bookingDurationMinutes' => 'nullable|integer|min:5|max:480',
            'licenseKeys' => 'nullable|string',
            'clearDigitalFile' => 'sometimes|boolean',
            'stock' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
            'image' => 'nullable|image|max:5120',
            'digitalFile' => 'nullable|file|max:20480|mimetypes:application/pdf,application/epub+zip,text/plain,text/csv,application/zip,application/x-zip-compressed,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
        if (array_key_exists('productType', $validated)
            && ($response = $this->catalogTypeEntitlementResponse((int) $product->company_id, $validated['productType']))) {
            return $response;
        }
        if (($validated['bookable'] ?? false)
            && ($response = $this->bookingEntitlementResponse((int) $product->company_id))) {
            return $response;
        }

        unset($validated['image']);
        unset($validated['digitalFile']);
        $licenseKeysRaw = $validated['licenseKeys'] ?? null;
        unset($validated['licenseKeys']);
        $clearDigitalFile = (bool) ($validated['clearDigitalFile'] ?? false);
        unset($validated['clearDigitalFile']);
        $updates = $this->normalizeProductPayload($validated, $request, true, $product);
        $product->update($updates);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products/'.$product->company_id, 'public');
            $product->update(['image' => $path]);
            $this->createImageRecord($product, null, $path, true, 0, $product->name);
        }

        if ($request->hasFile('digitalFile')) {
            $this->deleteExistingDigitalFile($product);
            $product->update($this->storeDigitalFile($request, (int) $product->company_id));
        } elseif ($clearDigitalFile) {
            $this->deleteExistingDigitalFile($product);
            $product->update([
                'digital_file_path' => null,
                'digital_file_name' => null,
                'digital_file_mime' => null,
                'digital_file_size' => null,
            ]);
        }

        $this->importLicenseKeysFromRequest($product, $licenseKeysRaw);

        $product->load($this->productRelations());
        $this->syncProductEmbeddings($product);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'product' => $this->productToArray($product),
        ]);
    }

    public function storeProductImage(Request $request, Product $product): JsonResponse
    {
        if ($product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'image' => 'required|image|max:5120',
            'isPrimary' => 'sometimes|boolean',
            'sortOrder' => 'sometimes|integer|min:0',
            'altText' => 'nullable|string|max:255',
        ]);

        $path = $request->file('image')->store('products/'.$product->company_id, 'public');
        $image = $this->createImageRecord(
            $product,
            null,
            $path,
            (bool) ($validated['isPrimary'] ?? false),
            (int) ($validated['sortOrder'] ?? 0),
            $validated['altText'] ?? $product->name
        );

        if ($product->image === null || $image->is_primary) {
            $product->update(['image' => $path]);
        }

        return response()->json([
            'success' => true,
            'image' => $this->productImageToArray($image->fresh()),
            'message' => 'Product image uploaded',
        ], 201);
    }

    public function storeVariantImage(Request $request, ProductVariant $productVariant): JsonResponse
    {
        $product = $productVariant->product;
        if (! $product || $product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'image' => 'required|image|max:5120',
            'isPrimary' => 'sometimes|boolean',
            'sortOrder' => 'sometimes|integer|min:0',
            'altText' => 'nullable|string|max:255',
        ]);

        $path = $request->file('image')->store('products/'.$product->company_id.'/variants', 'public');
        $image = $this->createImageRecord(
            $product,
            $productVariant,
            $path,
            (bool) ($validated['isPrimary'] ?? false),
            (int) ($validated['sortOrder'] ?? 0),
            $validated['altText'] ?? $productVariant->label
        );

        return response()->json([
            'success' => true,
            'image' => $this->productImageToArray($image->fresh()),
            'message' => 'Variant image uploaded',
        ], 201);
    }

    public function updateImage(Request $request, ProductImage $productImage): JsonResponse
    {
        $product = $productImage->product;
        if (! $product || $product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'isPrimary' => 'sometimes|boolean',
            'sortOrder' => 'sometimes|integer|min:0',
            'altText' => 'nullable|string|max:255',
        ]);

        $updates = [];
        if (array_key_exists('isPrimary', $validated)) {
            $updates['is_primary'] = (bool) $validated['isPrimary'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $updates['sort_order'] = (int) $validated['sortOrder'];
        }
        if (array_key_exists('altText', $validated)) {
            $updates['alt_text'] = $validated['altText'];
        }

        if ($updates !== []) {
            $productImage->update($updates);
        }

        if (($updates['is_primary'] ?? false) === true) {
            $this->setPrimaryImage($productImage);
        }

        if ($productImage->product_variant_id === null && $productImage->is_primary) {
            $product->update(['image' => $productImage->path]);
        }

        return response()->json([
            'success' => true,
            'image' => $this->productImageToArray($productImage->fresh()),
            'message' => 'Image updated',
        ]);
    }

    public function destroyImage(Request $request, ProductImage $productImage): JsonResponse
    {
        $product = $productImage->product;
        if (! $product || $product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $deletedPath = $productImage->path;
        $wasPrimaryProductImage = $productImage->product_variant_id === null && $productImage->is_primary;
        $productImage->delete();

        if ($deletedPath && Storage::disk('public')->exists($deletedPath)) {
            Storage::disk('public')->delete($deletedPath);
        }

        if ($wasPrimaryProductImage) {
            $fallback = ProductImage::where('product_id', $product->id)
                ->whereNull('product_variant_id')
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($fallback) {
                $this->setPrimaryImage($fallback);
                $product->update(['image' => $fallback->path]);
            } else {
                $product->update(['image' => null]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Image deleted']);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        if ($product->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $this->deleteExistingDigitalFile($product);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    private function productToArray(Product $product): array
    {
        if (! $product->relationLoaded('variants') || ! $product->relationLoaded('images')) {
            $product->load($this->productRelations());
        }

        $primary = $product->images->firstWhere('is_primary', true);
        $fallback = $primary ?? $product->images->first();
        $imageUrl = $fallback ? Storage::url($fallback->path) : ($product->image ? Storage::url($product->image) : null);

        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'description' => $product->description ?? '',
            'price' => (float) $product->price,
            'category' => $product->category ?? '',
            'productType' => $product->product_type ?? 'physical',
            'fulfillmentType' => $product->fulfillment_type ?? 'shipping',
            'image' => $imageUrl,
            'trackInventory' => (bool) $product->track_inventory,
            'requiresDeliveryAddress' => (bool) $product->requires_delivery_address,
            'accessUrl' => $product->access_url,
            'serviceBookingUrl' => $product->service_booking_url,
            'fulfillmentInstructions' => $product->fulfillment_instructions,
            'hasDigitalFile' => (bool) $product->digital_file_path,
            'digitalFileUrl' => null,
            'digitalFileName' => $product->digital_file_name,
            'digitalFileMime' => $product->digital_file_mime,
            'digitalFileSize' => $product->digital_file_size,
            'licenseKeyMode' => $product->license_key_mode ?: 'none',
            'licenseKeyPrefix' => $product->license_key_prefix,
            'accessExpiresDays' => $product->access_expires_days,
            'maxDownloads' => $product->max_downloads,
            'bookable' => (bool) $product->bookable,
            'bookingDurationMinutes' => $product->booking_duration_minutes,
            'licenseKeysAvailable' => ProductLicenseKey::query()
                ->where('product_id', $product->id)
                ->where('status', ProductLicenseKey::STATUS_AVAILABLE)
                ->count(),
            'stock' => $product->stock,
            'status' => $product->status,
            'createdAt' => $product->created_at->format('Y-m-d'),
            'images' => $product->images->map(fn (ProductImage $img) => $this->productImageToArray($img))->values()->all(),
            'variants' => $product->variants->map(fn (ProductVariant $v) => $this->variantToArray($v))->values()->all(),
        ];
    }

    private function variantToArray(ProductVariant $v): array
    {
        if (! $v->relationLoaded('images')) {
            $v->load('images');
        }

        $primary = $v->images->firstWhere('is_primary', true) ?? $v->images->first();

        return [
            'id' => (string) $v->id,
            'label' => $v->label,
            'price' => (float) $v->price,
            'stock' => (int) $v->stock,
            'status' => $v->status,
            'attributes' => $v->attributes ?? [],
            'sortOrder' => (int) $v->sort_order,
            'image' => $primary ? Storage::url($primary->path) : null,
            'images' => $v->images->map(fn (ProductImage $img) => $this->productImageToArray($img))->values()->all(),
        ];
    }

    private function productImageToArray(ProductImage $image): array
    {
        return [
            'id' => (string) $image->id,
            'productId' => (string) $image->product_id,
            'productVariantId' => $image->product_variant_id ? (string) $image->product_variant_id : null,
            'url' => Storage::url($image->path),
            'path' => $image->path,
            'altText' => $image->alt_text,
            'isPrimary' => (bool) $image->is_primary,
            'sortOrder' => (int) $image->sort_order,
        ];
    }

    private function createImageRecord(
        Product $product,
        ?ProductVariant $variant,
        string $path,
        bool $isPrimary,
        int $sortOrder,
        ?string $altText
    ): ProductImage {
        $image = ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'path' => $path,
            'alt_text' => $altText,
            'is_primary' => $isPrimary,
            'sort_order' => $sortOrder,
        ]);

        if ($isPrimary) {
            $this->setPrimaryImage($image);
        }

        return $image;
    }

    private function setPrimaryImage(ProductImage $image): void
    {
        ProductImage::where('product_id', $image->product_id)
            ->where(function ($q) use ($image) {
                if ($image->product_variant_id === null) {
                    $q->whereNull('product_variant_id');
                } else {
                    $q->where('product_variant_id', $image->product_variant_id);
                }
            })
            ->where('id', '!=', $image->id)
            ->update(['is_primary' => false]);

        if (! $image->is_primary) {
            $image->update(['is_primary' => true]);
        }
    }

    private function productRelations(): array
    {
        return [
            'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
            'variants' => fn ($q) => $q
                ->orderBy('sort_order')
                ->orderBy('id')
                ->with(['images' => fn ($iq) => $iq->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')]),
        ];
    }

    private function syncProductEmbeddings(Product $product): void
    {
        if ($product->status !== 'active') {
            return;
        }

        try {
            app(\App\Services\AI\KnowledgeChunkService::class)->syncProduct($product);
        } catch (\Throwable) {
            // Non-blocking — weekly sync command will backfill
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeProductPayload(array $validated, Request $request, bool $isUpdate = false, ?Product $current = null): array
    {
        $productType = $validated['productType'] ?? $current?->product_type ?? 'physical';
        $fulfillmentType = $validated['fulfillmentType'] ?? $current?->fulfillment_type ?? ($productType === 'physical' ? 'shipping' : ($productType === 'service' ? 'booking' : 'download'));
        $trackInventory = array_key_exists('trackInventory', $validated)
            ? (bool) $validated['trackInventory']
            : ($current?->track_inventory ?? ($productType === 'physical'));
        $requiresDelivery = array_key_exists('requiresDeliveryAddress', $validated)
            ? (bool) $validated['requiresDeliveryAddress']
            : ($current?->requires_delivery_address ?? ($productType === 'physical' && $fulfillmentType === 'shipping'));

        if (! in_array($productType, self::PRODUCT_TYPES, true)) {
            $productType = 'physical';
        }
        if (! in_array($fulfillmentType, self::FULFILLMENT_TYPES, true)) {
            $fulfillmentType = $productType === 'physical' ? 'shipping' : 'manual';
        }

        $licenseKeyMode = $validated['licenseKeyMode'] ?? $current?->license_key_mode ?? 'none';
        if (! in_array($licenseKeyMode, self::LICENSE_KEY_MODES, true)) {
            $licenseKeyMode = 'none';
        }

        $accessExpiresDays = array_key_exists('accessExpiresDays', $validated)
            ? ($validated['accessExpiresDays'] !== null && $validated['accessExpiresDays'] !== '' ? (int) $validated['accessExpiresDays'] : null)
            : $current?->access_expires_days;
        $maxDownloads = array_key_exists('maxDownloads', $validated)
            ? ($validated['maxDownloads'] !== null && $validated['maxDownloads'] !== '' ? (int) $validated['maxDownloads'] : null)
            : $current?->max_downloads;
        $bookable = array_key_exists('bookable', $validated)
            ? (bool) $validated['bookable']
            : ($current?->bookable ?? false);
        $bookingDurationMinutes = array_key_exists('bookingDurationMinutes', $validated)
            ? ($validated['bookingDurationMinutes'] !== null && $validated['bookingDurationMinutes'] !== ''
                ? (int) $validated['bookingDurationMinutes']
                : null)
            : $current?->booking_duration_minutes;

        return [
            'name' => $validated['name'] ?? $current?->name,
            'description' => $validated['description'] ?? $current?->description,
            'price' => $validated['price'] ?? $current?->price ?? 0,
            'category' => $validated['category'] ?? $current?->category,
            'product_type' => $productType,
            'fulfillment_type' => $fulfillmentType,
            'track_inventory' => $trackInventory,
            'requires_delivery_address' => $requiresDelivery,
            'access_url' => array_key_exists('accessUrl', $validated)
                ? (($validated['accessUrl'] ?? '') !== '' ? $validated['accessUrl'] : null)
                : $current?->access_url,
            'service_booking_url' => array_key_exists('serviceBookingUrl', $validated)
                ? (($validated['serviceBookingUrl'] ?? '') !== '' ? $validated['serviceBookingUrl'] : null)
                : $current?->service_booking_url,
            'fulfillment_instructions' => array_key_exists('fulfillmentInstructions', $validated)
                ? (($validated['fulfillmentInstructions'] ?? '') !== '' ? $validated['fulfillmentInstructions'] : null)
                : $current?->fulfillment_instructions,
            'license_key_mode' => $licenseKeyMode,
            'license_key_prefix' => array_key_exists('licenseKeyPrefix', $validated)
                ? (($validated['licenseKeyPrefix'] ?? '') !== '' ? $validated['licenseKeyPrefix'] : null)
                : $current?->license_key_prefix,
            'access_expires_days' => $accessExpiresDays,
            'max_downloads' => $maxDownloads,
            'bookable' => $bookable,
            'booking_duration_minutes' => $bookingDurationMinutes,
            'stock' => $validated['stock'] ?? $current?->stock ?? 0,
            'status' => $validated['status'] ?? $current?->status ?? 'active',
        ];
    }

    private function catalogTypeEntitlementResponse(int $companyId, string $productType): ?JsonResponse
    {
        $company = Company::findOrFail($companyId);
        if ($this->entitlements->allowsProductType($company, $productType)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'code' => 'catalog_type_required',
            'message' => ucfirst($productType).' products are not available on your current plan.',
        ], 403);
    }

    private function bookingEntitlementResponse(int $companyId): ?JsonResponse
    {
        $company = Company::findOrFail($companyId);
        if ($this->entitlements->allowsBookings($company)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'code' => 'bookings_required',
            'message' => 'Bookings are not available on your current plan.',
        ], 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeDigitalFile(Request $request, int $companyId): array
    {
        $file = $request->file('digitalFile');
        $path = $file->store('products/'.$companyId.'/digital', 'local');

        return [
            'digital_file_path' => $path,
            'digital_file_name' => $file->getClientOriginalName(),
            'digital_file_mime' => $file->getClientMimeType(),
            'digital_file_size' => $file->getSize(),
        ];
    }

    private function deleteExistingDigitalFile(Product $product): void
    {
        if (! $product->digital_file_path) {
            return;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($product->digital_file_path)) {
                Storage::disk($disk)->delete($product->digital_file_path);
            }
        }
    }

    private function importLicenseKeysFromRequest(Product $product, mixed $raw): void
    {
        if (! is_string($raw) || trim($raw) === '') {
            return;
        }

        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        app(DigitalAccessService::class)->importLicenseKeys($product, $parts);
    }
}
