<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestigationCase extends Model
{
    protected $fillable = [
        'company_id',
        'owner_analytics_investigation_id',
        'goal',
        'status',
        'current_step',
        'steps',
        'metadata',
        'closed_at',
    ];

    protected $casts = [
        'steps' => 'array',
        'metadata' => 'array',
        'closed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
