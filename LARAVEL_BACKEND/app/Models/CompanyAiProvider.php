<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class CompanyAiProvider extends Model
{
    protected $fillable = [
        'company_id',
        'ai_provider_id',
        'api_key',
        'api_base_url',
        'is_enabled',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'api_key',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public function hasConfiguredApiKey(): bool
    {
        try {
            return filled($this->api_key);
        } catch (DecryptException $e) {
            Log::warning('company_ai_provider.api_key_decrypt_failed', [
                'id' => $this->id,
                'company_id' => $this->company_id,
                'ai_provider_id' => $this->ai_provider_id,
            ]);

            return false;
        }
    }
}
