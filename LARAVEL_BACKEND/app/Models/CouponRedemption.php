<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    protected $fillable = [
        'subscription_offer_id',
        'company_id',
        'subscription_id',
        'payment_reference',
        'original_amount',
        'discount_amount',
        'final_amount',
        'currency',
        'status',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(SubscriptionOffer::class, 'subscription_offer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
