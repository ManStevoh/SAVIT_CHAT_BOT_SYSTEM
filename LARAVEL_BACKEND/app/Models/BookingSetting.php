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
        'payment_requirement',
        'whatsapp_booking_mode',
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

    public function requiresUpfrontPayment(): bool
    {
        return ($this->payment_requirement ?? 'at_venue') === 'required';
    }

    public function allowsPayAtVenue(): bool
    {
        return in_array($this->payment_requirement ?? 'at_venue', ['at_venue', 'optional'], true);
    }

    public function isWhatsAppNativeEnabled(): bool
    {
        return in_array($this->whatsapp_booking_mode ?? 'hybrid', ['whatsapp_native', 'hybrid'], true);
    }

    public function isWebLinkEnabled(): bool
    {
        return in_array($this->whatsapp_booking_mode ?? 'hybrid', ['web_link', 'hybrid'], true);
    }
}