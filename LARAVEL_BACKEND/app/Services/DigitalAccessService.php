<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductLicenseKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class DigitalAccessService
{
    /**
     * Prepare paid digital/service lines: hydrate from product, issue keys, copy private files, signed URLs.
     */
    public function preparePaidOrder(Order $order): void
    {
        $order->loadMissing('orderProducts.product', 'company');

        foreach ($order->orderProducts as $line) {
            $product = $line->product;
            if (! $product && ! empty($line->product_id)) {
                $product = Product::find($line->product_id);
            }

            $data = is_array($line->fulfillment_data) ? $line->fulfillment_data : [];
            $data = $this->hydrateFulfillmentData($data, $product);

            $type = (string) ($data['productType'] ?? 'physical');
            if ($type === 'physical') {
                if ($data !== ($line->fulfillment_data ?? [])) {
                    $line->update(['fulfillment_data' => $data ?: null]);
                }
                continue;
            }

            // Never trust client-supplied file paths — only product or previously order-owned copies.
            unset($data['digitalFilePath'], $data['digitalFileAbsolutePath']);
            if ($product?->digital_file_path) {
                $data['digitalFilePath'] = $product->digital_file_path;
                $data['digitalFileName'] = $product->digital_file_name;
                $data['digitalFileMime'] = $product->digital_file_mime;
                $data['digitalFileSize'] = $product->digital_file_size;
            }

            $expiresAt = $this->resolveExpiry($product, $data);
            $issuedKeys = $this->issueLicenseKeys($line, $product, $data);
            if ($issuedKeys !== []) {
                $data['licenseKeys'] = $issuedKeys;
            }

            if (! empty($data['digitalFilePath'])) {
                $copied = $this->copyDigitalFileForOrder($order, $line, (string) $data['digitalFilePath']);
                if ($copied) {
                    $data['digitalFilePath'] = $copied;
                }
                $data['digitalFileUrl'] = $this->signedDownloadUrl($line, $expiresAt);
            }

            $maxDownloads = $product?->max_downloads
                ?? (isset($data['maxDownloads']) ? (int) $data['maxDownloads'] : null);
            if ($maxDownloads !== null && $maxDownloads > 0) {
                $data['maxDownloads'] = (int) $maxDownloads;
            } else {
                unset($data['maxDownloads']);
            }
            $data['downloadCount'] = (int) ($line->download_count ?? 0);

            if ($product && $product->bookable) {
                $data['bookable'] = true;
                $company = $order->company ?? $product->company;
                if ($company) {
                    $data['bookingUrl'] = app(BookingService::class)->publicBookingUrl(
                        $company,
                        $product,
                        $order
                    );
                }
            }

            $data['accessExpiresAt'] = $expiresAt?->toIso8601String();
            unset($data['digitalFileAbsolutePath']);
            $line->update(['fulfillment_data' => $data]);
        }
    }

    /**
     * Atomically consume one download. Returns remaining allowance or null if unlimited.
     * Throws RuntimeException when exhausted or expired.
     */
    public function consumeDownload(OrderProduct $line): ?int
    {
        return DB::transaction(function () use ($line) {
            /** @var OrderProduct $locked */
            $locked = OrderProduct::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $data = is_array($locked->fulfillment_data) ? $locked->fulfillment_data : [];

            if ($this->lineAccessIsExpired($data)) {
                throw new \RuntimeException('This download link has expired.');
            }

            $max = isset($data['maxDownloads']) ? (int) $data['maxDownloads'] : null;
            $count = (int) $locked->download_count;
            if ($max !== null && $max > 0 && $count >= $max) {
                throw new \RuntimeException('Download limit reached. Purchase again for more downloads.');
            }

            $locked->update(['download_count' => $count + 1]);
            $data['downloadCount'] = $count + 1;
            $locked->update(['fulfillment_data' => $data]);

            if ($max === null || $max < 1) {
                return null;
            }

            return max(0, $max - ($count + 1));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function hydrateFulfillmentData(array $data, ?Product $product): array
    {
        if (! $product) {
            return $data;
        }

        $snapshot = $product->fulfillmentSnapshot();
        $type = (string) ($data['productType'] ?? '');
        if ($type === '' || $type === 'physical') {
            // Prefer live product type when line looks physical/empty but catalog says otherwise.
            if (($snapshot['productType'] ?? 'physical') !== 'physical') {
                return array_merge($snapshot, array_filter($data, fn ($v) => $v !== null && $v !== ''));
            }
        }

        foreach (['accessUrl', 'serviceBookingUrl', 'fulfillmentInstructions', 'licenseKeyMode', 'accessExpiresDays'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                if (array_key_exists($key, $snapshot) && $snapshot[$key] !== null && $snapshot[$key] !== '') {
                    $data[$key] = $snapshot[$key];
                }
            }
        }

        if (empty($data['productType'])) {
            $data['productType'] = $snapshot['productType'] ?? 'physical';
        }
        if (empty($data['fulfillmentType'])) {
            $data['fulfillmentType'] = $snapshot['fulfillmentType'] ?? 'shipping';
        }

        return $data;
    }

    public function signedAccessPortalUrl(Order $order): string
    {
        $expiry = $this->portalSignatureExpiry($order);

        return URL::temporarySignedRoute('orders.access', $expiry, ['order' => $order->id]);
    }

    public function signedDownloadUrl(OrderProduct $line, ?\DateTimeInterface $expiresAt = null): string
    {
        $expiry = $expiresAt
            ? \Carbon\Carbon::instance(\Carbon\Carbon::parse($expiresAt))
            : now()->addYears(10);

        if ($expiry->isPast()) {
            $expiry = now()->addSeconds(5);
        }

        return URL::temporarySignedRoute('orders.digital-download', $expiry, [
            'order' => $line->order_id,
            'orderProduct' => $line->id,
        ]);
    }

    public function orderAccessIsExpired(Order $order): bool
    {
        $order->loadMissing('orderProducts');
        $hasExpiring = false;
        $anyStillValid = false;

        foreach ($order->orderProducts as $line) {
            $data = is_array($line->fulfillment_data) ? $line->fulfillment_data : [];
            if (($data['productType'] ?? 'physical') === 'physical') {
                continue;
            }
            if (empty($data['accessExpiresAt'])) {
                $anyStillValid = true;
                continue;
            }
            $hasExpiring = true;
            if (! now()->greaterThan($data['accessExpiresAt'])) {
                $anyStillValid = true;
            }
        }

        return $hasExpiring && ! $anyStillValid;
    }

    public function lineAccessIsExpired(array $data): bool
    {
        if (empty($data['accessExpiresAt'])) {
            return false;
        }

        return now()->greaterThan($data['accessExpiresAt']);
    }

    public function resolveAbsolutePath(string $path): ?string
    {
        if (! $this->isAllowedDigitalPath($path)) {
            return null;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->path($path);
            }
        }

        return null;
    }

    public function resolveReadableStream(string $path): ?array
    {
        if (! $this->isAllowedDigitalPath($path)) {
            return null;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return [
                    'disk' => $disk,
                    'path' => $path,
                    'absolute' => Storage::disk($disk)->path($path),
                ];
            }
        }

        return null;
    }

    public function availableLicenseKeyCount(Product $product): int
    {
        return ProductLicenseKey::query()
            ->where('product_id', $product->id)
            ->where('status', ProductLicenseKey::STATUS_AVAILABLE)
            ->count();
    }

    /**
     * @return list<string>
     */
    public function issueLicenseKeys(OrderProduct $line, ?Product $product, ?array $existingData = null): array
    {
        $data = $existingData ?? (is_array($line->fulfillment_data) ? $line->fulfillment_data : []);
        if (! empty($data['licenseKeys']) && is_array($data['licenseKeys'])) {
            return array_values(array_filter(array_map('strval', $data['licenseKeys'])));
        }

        $mode = $product?->license_key_mode
            ?? ($data['licenseKeyMode'] ?? 'none');
        $qty = max(1, (int) $line->quantity);

        if ($mode === 'none' || ! $product) {
            return [];
        }

        if ($mode === 'auto') {
            $prefix = trim((string) ($product->license_key_prefix ?: 'KEY'));
            $keys = [];
            for ($i = 0; $i < $qty; $i++) {
                $keys[] = strtoupper($prefix).'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
            }
            $this->persistIssuedKeys($product, $line, $keys);

            return $keys;
        }

        if ($mode === 'pool') {
            return DB::transaction(function () use ($product, $line, $qty) {
                $available = ProductLicenseKey::query()
                    ->where('product_id', $product->id)
                    ->where('status', ProductLicenseKey::STATUS_AVAILABLE)
                    ->lockForUpdate()
                    ->limit($qty)
                    ->get();

                $keys = [];
                foreach ($available as $row) {
                    $row->update([
                        'status' => ProductLicenseKey::STATUS_ASSIGNED,
                        'order_product_id' => $line->id,
                        'assigned_at' => now(),
                    ]);
                    $keys[] = $row->license_key;
                }

                if (count($keys) < $qty) {
                    Log::warning('License key pool shortfall on paid order', [
                        'product_id' => $product->id,
                        'order_product_id' => $line->id,
                        'needed' => $qty,
                        'assigned' => count($keys),
                    ]);
                }

                return $keys;
            });
        }

        return [];
    }

    /**
     * @param  list<string>  $keys
     */
    public function importLicenseKeys(Product $product, array $keys): int
    {
        $created = 0;
        foreach ($keys as $raw) {
            $key = trim((string) $raw);
            if ($key === '') {
                continue;
            }
            $exists = ProductLicenseKey::query()
                ->where('product_id', $product->id)
                ->where('license_key', $key)
                ->exists();
            if ($exists) {
                continue;
            }
            ProductLicenseKey::create([
                'product_id' => $product->id,
                'license_key' => $key,
                'status' => ProductLicenseKey::STATUS_AVAILABLE,
            ]);
            $created++;
        }

        return $created;
    }

    public function assertPoolCapacity(Product $product, int $quantity): ?string
    {
        if (($product->license_key_mode ?: 'none') !== 'pool') {
            return null;
        }

        $available = $this->availableLicenseKeyCount($product);
        $qty = max(1, $quantity);
        if ($available < $qty) {
            return $available === 0
                ? 'No license keys are available for this product. Import keys before selling.'
                : "Only {$available} license key(s) available. Enter a smaller quantity or import more keys.";
        }

        return null;
    }

    /**
     * @param  list<string>  $keys
     */
    private function persistIssuedKeys(Product $product, OrderProduct $line, array $keys): void
    {
        foreach ($keys as $key) {
            ProductLicenseKey::create([
                'product_id' => $product->id,
                'license_key' => $key,
                'status' => ProductLicenseKey::STATUS_ASSIGNED,
                'order_product_id' => $line->id,
                'assigned_at' => now(),
            ]);
        }
    }

    private function resolveExpiry(?Product $product, array $data): ?\Carbon\Carbon
    {
        $days = $product?->access_expires_days
            ?? (isset($data['accessExpiresDays']) ? (int) $data['accessExpiresDays'] : null);

        if (! $days || $days < 1) {
            return null;
        }

        return now()->addDays($days);
    }

    private function portalSignatureExpiry(Order $order): \Carbon\Carbon
    {
        $order->loadMissing('orderProducts');
        $latest = null;

        foreach ($order->orderProducts as $line) {
            $data = is_array($line->fulfillment_data) ? $line->fulfillment_data : [];
            if (($data['productType'] ?? 'physical') === 'physical') {
                continue;
            }
            if (empty($data['accessExpiresAt'])) {
                return now()->addYears(10);
            }
            $at = \Carbon\Carbon::parse($data['accessExpiresAt']);
            if ($latest === null || $at->greaterThan($latest)) {
                $latest = $at;
            }
        }

        if ($latest === null) {
            return now()->addYears(10);
        }

        if ($latest->isPast()) {
            return now()->addSeconds(5);
        }

        return $latest;
    }

    private function copyDigitalFileForOrder(Order $order, OrderProduct $line, string $sourcePath): ?string
    {
        if (! $this->isAllowedDigitalPath($sourcePath)) {
            return null;
        }

        $dest = 'orders/'.$order->id.'/digital/'.$line->id.'/'.basename($sourcePath);

        // Already an order-owned copy.
        if (str_starts_with($sourcePath, 'orders/'.$order->id.'/')) {
            return $sourcePath;
        }

        foreach (['local', 'public'] as $disk) {
            if (! Storage::disk($disk)->exists($sourcePath)) {
                continue;
            }
            if (! Storage::disk('local')->exists($dest)) {
                Storage::disk('local')->put($dest, Storage::disk($disk)->get($sourcePath));
            }

            return $dest;
        }

        return null;
    }

    private function isAllowedDigitalPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', ltrim($path, '/'));
        if ($normalized === '' || str_contains($normalized, '..')) {
            return false;
        }

        return str_starts_with($normalized, 'products/')
            || str_starts_with($normalized, 'orders/');
    }
}
