<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationLearningSample extends Model
{
    public const SOURCE_OPENAI = 'openai';

    public const SOURCE_FAQ = 'faq';

    protected $fillable = [
        'company_id',
        'customer_message',
        'assistant_reply',
        'source',
        'chat_id',
        'message_id',
    ];

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
