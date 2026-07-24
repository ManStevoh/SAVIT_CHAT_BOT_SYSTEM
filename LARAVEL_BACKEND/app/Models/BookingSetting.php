<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSetting extends Model
{
    protected $fillable = [
        'company_id',
        'timezone',
        'default_duration_minutes',
        'buffer_minutes',
        'min_notice_minutes',
        'max_days_ahead',
        'public_slug',
        'calendar_feed_token',
        'calendar_webhook_url',
        'is_enabled',
    ];

    protected $casts = [
        'default_duration_minutes' => 'int',
        'buffer_minutes' => 'int',
        'min_notice_minutes' => 'int',
        'max_days_ahead' => 'int',
        'is_enabled' => 'bool',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}