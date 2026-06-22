<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthIntegration extends Model
{
    protected $fillable = [
        'company_id',
        'provider',
        'status',
        'config',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'config' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
