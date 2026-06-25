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
        'status',
        'whatsapp_message_id',
        'attachment_url',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function learningSample(): BelongsTo
    {
        return $this->belongsTo(ConversationLearningSample::class, 'learning_sample_id');
    }
}
