<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StorefrontCustomer extends Authenticatable
{
    protected $fillable = [
        'company_id', 'phone', 'email', 'name', 'password', 'locale', 'last_order_at',
        'terms_accepted_at', 'marketing_consent', 'marketing_consent_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'last_order_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
        'marketing_consent' => 'boolean',
        'marketing_consent_at' => 'datetime',
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
