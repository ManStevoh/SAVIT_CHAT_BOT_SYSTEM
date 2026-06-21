<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthLearningPattern extends Model
{
    protected $fillable = [
        'company_id',
        'pattern_type',
        'source',
        'title',
        'body',
        'metrics',
        'confidence_score',
        'is_applied',
        'applied_count',
    ];

    protected $casts = [
        'metrics' => 'array',
        'confidence_score' => 'decimal:2',
        'is_applied' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
