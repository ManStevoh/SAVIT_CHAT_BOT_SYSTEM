<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class AiProvider extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'api_key',
        'api_base_url',
        'is_enabled',
        'config',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    protected $hidden = [
        'api_key',
    ];

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

    public function hasConfiguredApiKey(): bool
    {
        try {
            return filled($this->api_key);
        } catch (DecryptException $e) {
            Log::warning('ai_provider.api_key_decrypt_failed', [
                'provider_id' => $this->id,
                'slug' => $this->slug,
            ]);

            return false;
        }
    }
}
