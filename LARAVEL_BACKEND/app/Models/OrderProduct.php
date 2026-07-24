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
        'fulfillment_data',
        'download_count',
    ];

    protected $casts = [
        'price' => 'decimal:2',
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
}
