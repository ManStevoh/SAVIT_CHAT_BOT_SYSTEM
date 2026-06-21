<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributionEvent extends Model
{
    protected $fillable = [
        'company_id',
        'social_post_id',
        'attribution_link_id',
        'chat_id',
        'order_id',
        'event_type',
        'platform',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'referrer',
        'ip_hash',
        'user_agent_hash',
        'metadata',
        'revenue',
    ];

    protected $casts = [
        'metadata' => 'array',
        'revenue' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    public function attributionLink(): BelongsTo
    {
        return $this->belongsTo(AttributionLink::class);
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
