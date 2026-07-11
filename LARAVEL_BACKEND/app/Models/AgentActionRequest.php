<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentActionRequest extends Model
{
    protected $fillable = [
        'company_id', 'chat_id', 'action_type', 'risk_level', 'payload',
        'reasoning', 'execution_result', 'status', 'approved_by', 'approved_at', 'rejected_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'execution_result' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
