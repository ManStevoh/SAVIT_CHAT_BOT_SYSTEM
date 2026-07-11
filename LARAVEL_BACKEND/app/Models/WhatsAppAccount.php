<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class WhatsAppAccount extends Model
{
    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'company_id',
        'phone_number_id',
        'whatsapp_business_account_id',
        'meta_billing_model',
        'credit_allocation_config_id',
        'credit_line_shared_at',
        'access_token',
        'verify_token',
        'status',
        'onboarding_status',
        'onboarding_error',
        'webhook_subscribed_at',
        'phone_registered_at',
        'display_name_status',
        'quality_rating',
        'registration_pin',
        'display_phone_number',
        'connected_at',
        'disconnected_at',
    ];

    protected $casts = [
        'webhook_subscribed_at' => 'datetime',
        'credit_line_shared_at' => 'datetime',
        'phone_registered_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'registration_pin',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getAccessTokenAttribute(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
