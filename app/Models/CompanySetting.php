<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_id',
        'whatsapp_number',
        'ai_greeting',
        'ai_tone',
        'auto_reply_enabled',
        'notifications_enabled',
    ];

    protected $casts = [
        'auto_reply_enabled' => 'boolean',
        'notifications_enabled' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
