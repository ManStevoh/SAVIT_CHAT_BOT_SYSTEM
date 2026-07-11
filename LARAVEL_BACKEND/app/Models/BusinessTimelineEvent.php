<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTimelineEvent extends Model
{
    protected $fillable = [
        'company_id',
        'event_type',
        'category',
        'title',
        'summary',
        'payload',
        'source_type',
        'source_id',
        'importance',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'importance' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
