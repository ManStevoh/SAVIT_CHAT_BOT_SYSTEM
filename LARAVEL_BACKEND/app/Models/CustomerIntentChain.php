<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerIntentChain extends Model
{
    protected $fillable = [
        'company_id',
        'customer_phone',
        'primary_intent',
        'stage',
        'journey',
        'last_active_at',
    ];

    protected $casts = [
        'journey' => 'array',
        'last_active_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
