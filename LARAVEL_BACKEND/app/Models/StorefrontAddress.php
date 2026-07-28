<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontAddress extends Model
{
    protected $fillable = [
        'company_id', 'storefront_customer_id', 'label', 'line', 'city', 'notes', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(StorefrontCustomer::class, 'storefront_customer_id');
    }
}
