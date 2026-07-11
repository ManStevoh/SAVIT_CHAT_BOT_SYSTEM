<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyModuleInstallation extends Model
{
    protected $fillable = [
        'company_id',
        'module_key',
        'status',
        'config',
        'installed_at',
    ];

    protected $casts = [
        'config' => 'array',
        'installed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(MarketplaceModule::class, 'module_key', 'module_key');
    }
}
