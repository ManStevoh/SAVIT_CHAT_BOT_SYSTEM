<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class SocialAccount extends Model
{
    protected $fillable = [
        'company_id',
        'platform',
        'account_name',
        'external_account_id',
        'page_id',
        'ad_account_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'status',
        'metadata',
        'connected_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function getAccessTokenAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
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

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }
}
