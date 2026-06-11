<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPostMetric extends Model
{
    protected $fillable = [
        'social_post_id',
        'recorded_at',
        'reach',
        'impressions',
        'likes',
        'comments',
        'shares',
        'clicks',
        'saves',
        'engagement_rate',
        'raw_data',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'engagement_rate' => 'decimal:4',
        'raw_data' => 'array',
    ];

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }
}
