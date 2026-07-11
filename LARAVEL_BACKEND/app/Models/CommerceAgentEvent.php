<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceAgentEvent extends Model
{
    protected $fillable = [
        'company_id', 'event_type', 'event_key',
        'payload', 'status', 'handled_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'handled_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
