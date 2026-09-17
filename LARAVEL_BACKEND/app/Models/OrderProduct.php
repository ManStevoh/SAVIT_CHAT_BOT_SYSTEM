<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderProduct extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'name',
        'quantity',
        'price',
        'tax_rate_id',
        'tax_name',
        'tax_code',
        'tax_rate',
        'tax_inclusive',
        'tax_amount',
        'line_subtotal',
        'fulfillment_data',
        'download_count',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_inclusive' => 'boolean',
        'tax_amount' => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'fulfillment_data' => 'array',
        'download_count' => 'int',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function catalogProductType(): string
    {
        $data = is_array($this->fulfillment_data) ? $this->fulfillment_data : [];
        $type = strtolower((string) ($data['productType'] ?? $this->product?->product_type ?? ''));
        if (in_array($type, ['physical', 'digital', 'service'], true)) {
            return $type;
        }
        $fulfillment = strtolower((string) ($data['fulfillmentType'] ?? $this->product?->fulfillment_type ?? ''));
        if (in_array($fulfillment, ['download', 'link'], true)) {
            return 'digital';
        }
        if ($fulfillment === 'booking') {
            return 'service';
        }

        return 'physical';
    }

    public function catalogFulfillmentType(): string
    {
        $data = is_array($this->fulfillment_data) ? $this->fulfillment_data : [];

        return strtolower((string) ($data['fulfillmentType'] ?? $this->product?->fulfillment_type ?? ''));
    }

    public function needsPhysicalShipping(): bool
    {
        $type = $this->catalogProductType();
        $fulfillment = $this->catalogFulfillmentType();
        if (in_array($type, ['digital', 'service'], true)) {
            return false;
        }
        if (in_array($fulfillment, ['download', 'link', 'booking'], true)) {
            return false;
        }

        return true;
    }

    public function scopeWhereNeedsPhysicalShipping($query)
    {
        return $query->where(function ($line) {
            $line->where(function ($jsonType) {
                $jsonType->whereNull('fulfillment_data')
                    ->orWhereNull('fulfillment_data->productType')
                    ->orWhereNotIn('fulfillment_data->productType', ['digital', 'service']);
            })->where(function ($jsonFulfill) {
                $jsonFulfill->whereNull('fulfillment_data')
                    ->orWhereNull('fulfillment_data->fulfillmentType')
                    ->orWhereNotIn('fulfillment_data->fulfillmentType', ['download', 'link', 'booking']);
            })->where(function ($catalog) {
                $catalog->whereDoesntHave('product')
                    ->orWhereHas('product', function ($p) {
                        $p->where(function ($t) {
                            $t->whereNull('product_type')->orWhere('product_type', 'physical');
                        })->where(function ($f) {
                            $f->whereNull('fulfillment_type')
                                ->orWhereNotIn('fulfillment_type', ['download', 'link', 'booking']);
                        });
                    });
            });
        });
    }
}
