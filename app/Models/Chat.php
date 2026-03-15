<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    protected $fillable = [
        'company_id',
        'customer_name',
        'customer_phone',
        'customer_avatar',
        'last_message',
        'last_message_at',
        'unread_count',
        'status',
        'ai_handled',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'ai_handled' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
