<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    protected $fillable = [
        'company_id',
        'channel',
        'channel_user_id',
        'social_post_id',
        'attribution_link_id',
        'customer_name',
        'customer_phone',
        'detected_language',
        'detected_sentiment',
        'customer_avatar',
        'last_message',
        'last_message_at',
        'unread_count',
        'status',
        'ai_handled',
        'agent_handling_at',
        'crm_last_follow_up_at',
        'crm_follow_up_count',
        'conversation_step',
        'order_draft',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'ai_handled' => 'boolean',
        'agent_handling_at' => 'datetime',
        'crm_last_follow_up_at' => 'datetime',
        'crm_follow_up_count' => 'integer',
        'order_draft' => 'array',
    ];

    /** Whether an agent is currently handling this chat (bot should not auto-reply). */
    public function isAgentHandling(int $withinMinutes = 30): bool
    {
        if (! $this->agent_handling_at) {
            return false;
        }
        return $this->agent_handling_at->diffInMinutes(now(), false) < $withinMinutes;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    public function attributionLink(): BelongsTo
    {
        return $this->belongsTo(AttributionLink::class);
    }
}
