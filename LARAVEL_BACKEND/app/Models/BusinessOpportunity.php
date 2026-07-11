<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessOpportunity extends Model
{
    protected $fillable = [
        'company_id', 'opportunity_type', 'title', 'description',
        'evidence', 'estimated_impact', 'status', 'priority', 'detected_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'estimated_impact' => 'array',
        'detected_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
