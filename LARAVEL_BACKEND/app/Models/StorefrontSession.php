<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StorefrontSession extends Model
{
    protected $fillable = [
        'company_id',
        'session_token',
        'customer_name',
        'customer_phone',
        'customer_email',
        'cart',
        'fulfillment_type',
        'dine_in_table_id',
        'last_activity_at',
        'abandoned_notified_at',
        'wishlist',
        'locale',
        'coupon_code',
    ];

    protected $casts = [
        'cart' => 'array',
        'wishlist' => 'array',
        'last_activity_at' => 'datetime',
        'abandoned_notified_at' => 'datetime',
    ];

    /** @return list<int> */
    public function wishlistIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->wishlist ?? [])));
    }

    protected static function booted(): void
    {
        static::creating(function (StorefrontSession $session): void {
            if (empty($session->session_token)) {
                $session->session_token = Str::random(48);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function dineInTable(): BelongsTo
    {
        return $this->belongsTo(DineInTable::class, 'dine_in_table_id');
    }
}
