<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'meta_title',
        'meta_description',
        'price',
        'compare_at_price',
        'tax_rate_id',
        'category',
        'product_type',
        'fulfillment_type',
        'image',
        'track_inventory',
        'requires_delivery_address',
        'access_url',
        'service_booking_url',
        'fulfillment_instructions',
        'digital_file_path',
        'digital_file_name',
        'digital_file_mime',
        'digital_file_size',
        'license_key_mode',
        'license_key_prefix',
        'access_expires_days',
        'max_downloads',
        'bookable',
        'booking_duration_minutes',
        'stock',
        'status',
        'is_subscription',
        'subscription_interval',
        'catalog_embedding',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'track_inventory' => 'bool',
        'requires_delivery_address' => 'bool',
        'is_subscription' => 'bool',
        'digital_file_size' => 'int',
        'access_expires_days' => 'int',
        'max_downloads' => 'int',
        'bookable' => 'bool',
        'booking_duration_minutes' => 'int',
        'catalog_embedding' => 'array',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        $cleanPath = ltrim($this->image, '/');
        if (! str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = 'storage/' . $cleanPath;
        }

        return url($cleanPath);
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            if (empty($product->slug) && ! empty($product->name)) {
                $product->slug = static::generateUniqueSlug($product->company_id, $product->name);
            }
        });

        static::updating(function (Product $product): void {
            if (empty($product->slug) && ! empty($product->name)) {
                $product->slug = static::generateUniqueSlug($product->company_id, $product->name, $product->id);
            }
        });
    }

    public static function generateUniqueSlug(?int $companyId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'product';
        }

        $slug = $base;
        $suffix = 2;
        while (
            static::where('company_id', $companyId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function usesInventory(): bool
    {
        return (bool) $this->track_inventory;
    }

    public function isPhysical(): bool
    {
        return $this->product_type === 'physical';
    }

    public function isDigital(): bool
    {
        return $this->product_type === 'digital';
    }

    public function isService(): bool
    {
        return $this->product_type === 'service';
    }

    public function isBundle(): bool
    {
        return $this->product_type === 'bundle';
    }

    /** @return string|null Human label for the subscription interval (e.g. "Weekly"). */
    public function subscriptionIntervalLabel(): ?string
    {
        return match ($this->subscription_interval) {
            'week' => 'Weekly',
            'month' => 'Monthly',
            default => null,
        };
    }

    public function fulfillmentSnapshot(?ProductVariant $variant = null): array
    {
        return [
            'productType' => $this->product_type ?: 'physical',
            'fulfillmentType' => $this->fulfillment_type ?: 'shipping',
            'trackInventory' => (bool) $this->track_inventory,
            'requiresDeliveryAddress' => (bool) $this->requires_delivery_address,
            'accessUrl' => $this->access_url,
            'serviceBookingUrl' => $this->service_booking_url,
            'fulfillmentInstructions' => $this->fulfillment_instructions,
            // Public URL intentionally omitted — downloads use signed routes after payment.
            'digitalFileUrl' => null,
            'digitalFilePath' => $this->digital_file_path,
            'digitalFileName' => $this->digital_file_name,
            'digitalFileMime' => $this->digital_file_mime,
            'digitalFileSize' => $this->digital_file_size,
            'licenseKeyMode' => $this->license_key_mode ?: 'none',
            'licenseKeyPrefix' => $this->license_key_prefix,
            'accessExpiresDays' => $this->access_expires_days,
            'maxDownloads' => $this->max_downloads,
            'bookable' => (bool) $this->bookable,
            'bookingDurationMinutes' => $this->booking_duration_minutes,
            'variantId' => $variant?->id,
            'variantLabel' => $variant?->label,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function licenseKeys(): HasMany
    {
        return $this->hasMany(ProductLicenseKey::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('status', 'active')->orderBy('sort_order')->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    public function primaryImage(): ?ProductImage
    {
        return $this->images()->where('is_primary', true)->first();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true)->latest();
    }

    /** Bundle items when this product is itself a bundle (parent side). */
    public function bundleItems(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_product_id');
    }
}
