<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentReasoningTrace extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'chat_id',
        'incoming_message',
        'trace',
        'chosen_plan',
        'latency_ms',
        'created_at',
    ];

    protected $casts = [
        'trace' => 'array',
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
