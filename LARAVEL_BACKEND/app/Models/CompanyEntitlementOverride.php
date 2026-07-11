<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyEntitlementOverride extends Model
{
    protected $fillable = ['company_id', 'overrides', 'notes'];

    protected $casts = [
        'overrides' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
