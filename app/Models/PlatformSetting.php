<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'platform_name',
        'support_email',
        'maintenance_mode',
        'ai_model',
        'max_tokens_per_request',
        'rate_limit_per_minute',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
    ];
}
