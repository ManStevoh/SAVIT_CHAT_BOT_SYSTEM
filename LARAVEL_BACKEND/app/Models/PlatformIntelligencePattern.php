<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformIntelligencePattern extends Model
{
    protected $fillable = [
        'pattern_key', 'pattern_type', 'description',
        'evidence_count', 'industries', 'metrics',
    ];

    protected $casts = [
        'industries' => 'array',
        'metrics' => 'array',
    ];
}
