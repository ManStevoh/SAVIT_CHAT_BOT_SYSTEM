<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthAdSpendEntry extends Model
{
    protected $fillable = [
        'company_id',
        'platform',
        'campaign_name',
        'amount',
        'currency',
        'spent_at',
        'source',
        'external_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_at' => 'date',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
