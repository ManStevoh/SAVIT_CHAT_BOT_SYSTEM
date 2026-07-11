<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyBrainSnapshot extends Model
{
    protected $fillable = [
        'company_id', 'snapshot_at', 'commerce_data', 'growth_data',
        'summary_text', 'digest',
    ];

    protected $casts = [
        'snapshot_at' => 'datetime',
        'commerce_data' => 'array',
        'growth_data' => 'array',
        'digest' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
