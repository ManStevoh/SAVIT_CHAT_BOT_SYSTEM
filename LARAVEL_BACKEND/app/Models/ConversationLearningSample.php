<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationLearningSample extends Model
{
    public const SOURCE_OPENAI = 'openai';

    public const SOURCE_FAQ = 'faq';

    public const SOURCE_AGENT = 'agent';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'company_id',
        'customer_message',
        'question_fingerprint',
        'assistant_reply',
        'source',
        'status',
        'language',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'chat_id',
        'message_id',
        'question_embedding',
        'use_count',
        'positive_feedback_count',
        'negative_feedback_count',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'last_used_at' => 'datetime',
            'question_embedding' => 'array',
            'use_count' => 'integer',
            'positive_feedback_count' => 'integer',
            'negative_feedback_count' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
