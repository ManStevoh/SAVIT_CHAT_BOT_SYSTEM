<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontCoupon extends Model
{
    protected $fillable = [
        'company_id', 'code', 'type', 'value', 'min_order',
        'max_redemptions', 'redeemed_count', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order' => 'decimal:2',
        'max_redemptions' => 'integer',
        'redeemed_count' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
        if ($this->max_redemptions !== null && $this->redeemed_count >= $this->max_redemptions) {
            return false;
        }

        return true;
    }

    public function discountForSubtotal(float $subtotal): float
    {
        if ($this->min_order !== null && $subtotal < (float) $this->min_order) {
            return 0.0;
        }
        if ($this->type === 'percent') {
            return round($subtotal * ((float) $this->value) / 100, 2);
        }

        return min($subtotal, (float) $this->value);
    }
}
