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
        'pushed_to_owner_at',
    ];

    protected $casts = [
        'brief_date' => 'date',
        'metrics' => 'array',
        'recommendations' => 'array',
        'executive_decisions' => 'array',
        'pushed_to_owner_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
