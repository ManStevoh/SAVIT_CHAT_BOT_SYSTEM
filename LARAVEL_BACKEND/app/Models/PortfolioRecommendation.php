<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioRecommendation extends Model
{
    protected $fillable = [
        'company_id',
        'recommendation_type',
        'industry_cluster',
        'title',
        'body',
        'confidence_score',
        'approved_for_tenants',
        'data',
        'is_read',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'approved_for_tenants' => 'boolean',
        'confidence_score' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
