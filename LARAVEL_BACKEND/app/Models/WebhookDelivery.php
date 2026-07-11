<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'webhook_endpoint_id', 'company_id', 'event_type', 'attempt', 'response_code',
        'status', 'payload', 'error', 'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
    ];
