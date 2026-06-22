<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessageTemplate extends Model
{
    protected $table = 'whatsapp_message_templates';

    protected $fillable = [
        'company_id',
        'meta_template_id',
        'name',
        'language',
        'category',
        'status',
        'components',
        'body_preview',
        'rejection_reason',
    ];

    protected $casts = [
        'components' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
