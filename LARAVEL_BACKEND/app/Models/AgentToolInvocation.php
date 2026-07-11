<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentToolInvocation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'chat_id',
        'tool_name',
        'arguments',
        'result',
        'duration_ms',
        'success',
        'created_at',
    ];

    protected $casts = [
        'arguments' => 'array',
        'result' => 'array',
        'success' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }
}
