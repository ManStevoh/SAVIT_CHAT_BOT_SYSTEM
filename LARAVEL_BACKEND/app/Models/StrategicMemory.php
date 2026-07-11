<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategicMemory extends Model
{
    protected $fillable = [
        'company_id', 'strategy_type', 'title', 'context_summary',
        'outcome_summary', 'success_score', 'evidence',
    ];

    protected $casts = [
        'evidence' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
