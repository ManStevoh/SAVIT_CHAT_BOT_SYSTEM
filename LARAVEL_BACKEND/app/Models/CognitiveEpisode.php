<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CognitiveEpisode extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id', 'chat_id', 'perception', 'debate', 'confidence',
        'confidence_action', 'critique', 'governance', 'outcome', 'created_at',
    ];

    protected $casts = [
        'perception' => 'array',
        'debate' => 'array',
        'critique' => 'array',
        'governance' => 'array',
        'confidence' => 'decimal:2',
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
