<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price_display',
        'price_amount',
        'description',
        'features',
        'popular',
        'cta',
        'sort_order',
        'stripe_price_id',
        'is_free',
        'has_trial',
        'trial_days',
        'trial_elapsed_action',
    ];

    protected $casts = [
        'price_amount' => 'decimal:2',
        'popular' => 'boolean',
        'features' => 'array',
        'is_free' => 'boolean',
        'has_trial' => 'boolean',
    ];
}
