<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SocialPost extends Model
{
    protected $fillable = [
        'company_id',
        'social_account_id',
        'platform',
        'title',
        'content',
        'content_type',
        'media_urls',
        'hashtags',
        'status',
        'scheduled_at',
        'published_at',
        'external_post_id',
        'publish_error',
        'utm_campaign',
        'utm_source',
        'utm_medium',
        'ai_generated',
        'approved_by',
        'approved_at',
        'performance_score',
        'predicted_revenue_score',
        'content_tags',
        'prediction_factors',
    ];

    protected $casts = [
        'media_urls' => 'array',
        'hashtags' => 'array',
        'content_tags' => 'array',
        'prediction_factors' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'approved_at' => 'datetime',
        'ai_generated' => 'boolean',
        'performance_score' => 'decimal:2',
        'predicted_revenue_score' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(SocialPostMetric::class);
    }

    public function attributionLink(): HasOne
    {
        return $this->hasOne(AttributionLink::class);
    }

    public function attributionEvents(): HasMany
    {
        return $this->hasMany(AttributionEvent::class);
    }

    public function latestMetrics(): HasOne
    {
        return $this->hasOne(SocialPostMetric::class)->latestOfMany('recorded_at');
    }
}
