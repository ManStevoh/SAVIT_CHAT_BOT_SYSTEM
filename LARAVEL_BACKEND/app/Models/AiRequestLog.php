<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'ai_provider_id',
        'ai_model_id',
        'chat_id',
        'use_case',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'billed_cost_usd',
        'latency_ms',
        'success',
        'http_status',
        'error_message',
        'credential_source',
        'selection_source',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
