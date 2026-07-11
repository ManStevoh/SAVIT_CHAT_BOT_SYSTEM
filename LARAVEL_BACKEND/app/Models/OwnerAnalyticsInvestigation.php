<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerAnalyticsInvestigation extends Model
{
    protected $fillable = [
        'company_id', 'question', 'period', 'status',
        'evidence', 'findings', 'recommendations',
        'confidence', 'model_used',
    ];

    protected $casts = [
        'evidence' => 'array',
        'findings' => 'array',
        'recommendations' => 'array',
        'confidence' => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
