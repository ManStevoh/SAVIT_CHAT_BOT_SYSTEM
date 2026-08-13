<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StorefrontCustomer extends Authenticatable
{
    protected $fillable = [
        'company_id', 'phone', 'email', 'name', 'password', 'locale', 'last_order_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
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
