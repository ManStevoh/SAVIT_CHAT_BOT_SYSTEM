<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorSnapshot extends Model
{
    protected $fillable = [
        'competitor_profile_id',
        'recorded_at',
        'follower_count',
        'avg_engagement',
        'top_hashtags',
        'notes',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'avg_engagement' => 'decimal:4',
        'top_hashtags' => 'array',
        'notes' => 'array',
    ];

    public function competitorProfile(): BelongsTo
    {
        return $this->belongsTo(CompetitorProfile::class);
    }
}
