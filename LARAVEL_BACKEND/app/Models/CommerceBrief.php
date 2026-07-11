<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceBrief extends Model
{
    protected $fillable = [
        'company_id',
        'brief_date',
        'summary',
        'metrics',
        'recommendations',
        'executive_decisions',
    ];

    protected $casts = [
        'brief_date' => 'date',
        'metrics' => 'array',
        'recommendations' => 'array',
        'executive_decisions' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
