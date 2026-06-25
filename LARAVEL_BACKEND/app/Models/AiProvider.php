<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        return filled($this->api_key);
    }
}
