<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainEvent extends Model
{
    protected $fillable = [
        'company_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'dispatched_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'dispatched_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
