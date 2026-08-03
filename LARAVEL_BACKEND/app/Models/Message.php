<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'content',
        'message_type',
        'sender',
        'reply_source',
        'learning_feedback',
        'learning_sample_id',
        'ai_request_log_id',
        'status',
        'whatsapp_message_id',
        'reply_to_message_id',
        'attachment_url',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'voice_transcript',
        'voice_duration',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function learningSample(): BelongsTo
    {
        return $this->belongsTo(ConversationLearningSample::class, 'learning_sample_id');
    }

    public function aiRequestLog(): BelongsTo
    {
        return $this->belongsTo(AiRequestLog::class, 'ai_request_log_id');
    }

    /**
     * Whether $next should replace the current delivery status (monotonic progression).
     */
    public static function shouldAdvanceStatus(?string $current, string $next): bool
    {
        $next = strtolower($next);
        $rank = [
            'failed' => 0,
            'sent' => 1,
            'delivered' => 2,
            'read' => 3,
            'received' => 1,
        ];

        if (! array_key_exists($next, $rank)) {
            return false;
        }

        if ($next === 'failed') {
            return true;
        }

        $currentRank = $rank[strtolower((string) $current)] ?? -1;

        return $rank[$next] >= $currentRank;
    }
}
