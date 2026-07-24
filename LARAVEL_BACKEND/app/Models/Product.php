<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'price',
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
        'catalog_embedding',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'track_inventory' => 'bool',
        'requires_delivery_address' => 'bool',
        'digital_file_size' => 'int',
        'access_expires_days' => 'int',
        'max_downloads' => 'int',
        'bookable' => 'bool',
        'booking_duration_minutes' => 'int',
        'catalog_embedding' => 'array',
    ];

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
}
