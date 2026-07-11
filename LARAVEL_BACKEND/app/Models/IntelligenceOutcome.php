<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceOutcome extends Model
{
    protected $fillable = [
        'company_id',
        'source_type',
        'source_id',
        'recommendation_key',
        'recommended_action',
        'outcome',
        'notes',
        'metrics',
        'recorded_by',
        'measured_at',
    ];

    protected $casts = [
        'metrics' => 'array',
        'measured_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
