<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBundleItem extends Model
{
    protected $fillable = [
        'company_id', 'bundle_product_id', 'child_product_id', 'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'child_product_id');
    }
}
