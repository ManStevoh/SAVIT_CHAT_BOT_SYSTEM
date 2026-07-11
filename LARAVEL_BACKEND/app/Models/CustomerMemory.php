<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMemory extends Model
{
    protected $fillable = [
        'company_id',
        'customer_phone',
        'memory_key',
        'memory_value',
        'category',
        'confidence',
        'source',
    ];

    protected $casts = [
        'confidence' => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
