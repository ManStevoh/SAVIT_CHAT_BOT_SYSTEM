<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessProbabilityScore extends Model
{
    protected $fillable = [
        'company_id',
        'customer_phone',
        'score_type',
        'probability',
        'factors',
        'computed_at',
    ];

    protected $casts = [
        'probability' => 'float',
        'factors' => 'array',
        'computed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
