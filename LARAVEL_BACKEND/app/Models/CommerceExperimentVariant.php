<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceExperimentVariant extends Model
{
    protected $fillable = [
        'experiment_id', 'variant_key', 'label', 'payload',
        'assignments_count', 'conversions_count', 'revenue_total',
    ];

    protected $casts = [
        'payload' => 'array',
        'revenue_total' => 'decimal:2',
    ];

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(CommerceExperiment::class, 'experiment_id');
    }

    public function conversionRate(): float
    {
        if ($this->assignments_count <= 0) {
            return 0.0;
        }

        return round(($this->conversions_count / $this->assignments_count) * 100, 2);
    }
}
