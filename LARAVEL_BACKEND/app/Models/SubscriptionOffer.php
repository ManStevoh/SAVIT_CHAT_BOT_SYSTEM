<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionOffer extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'discount_type',
        'discount_value',
        'currency',
        'plan_id',
        'max_redemptions',
        'redemption_count',
        'max_per_company',
        'starts_at',
        'ends_at',
        'is_active',
        'first_payment_only',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'first_payment_only' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isCurrentlyValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }
        if ($this->max_redemptions !== null && $this->redemption_count >= $this->max_redemptions) {
            return false;
        }

        return true;
    }
}
