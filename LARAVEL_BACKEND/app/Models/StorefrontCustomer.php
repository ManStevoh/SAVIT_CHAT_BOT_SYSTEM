<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontCustomer extends Model
{
    protected $fillable = [
        'company_id', 'phone', 'email', 'name', 'locale', 'last_order_at',
    ];

    protected $casts = [
        'last_order_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(StorefrontAddress::class);
    }
}
