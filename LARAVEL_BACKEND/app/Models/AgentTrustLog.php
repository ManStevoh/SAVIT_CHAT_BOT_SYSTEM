<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTrustLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id', 'chat_id', 'action_type', 'goal', 'reasoning_summary',
        'tools_used', 'data_consulted', 'confidence', 'outcome', 'explainability', 'created_at',
    ];

    protected $casts = [
        'tools_used' => 'array',
        'data_consulted' => 'array',
        'explainability' => 'array',
        'confidence' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
