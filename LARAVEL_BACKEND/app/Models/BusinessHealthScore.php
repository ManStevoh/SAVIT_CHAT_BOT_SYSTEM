<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessHealthScore extends Model
{
    protected $fillable = [
        'company_id', 'score_date', 'overall_score', 'factors', 'trends', 'summary',
    ];

    protected $casts = [
        'score_date' => 'date',
        'factors' => 'array',
        'trends' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
