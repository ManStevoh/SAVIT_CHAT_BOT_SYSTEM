<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyIntegration extends Model
{
    protected $fillable = [
        'company_id', 'connector_type', 'status', 'config', 'last_sync_at', 'last_error',
    ];

    protected $casts = [
        'config' => 'array',
        'last_sync_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
