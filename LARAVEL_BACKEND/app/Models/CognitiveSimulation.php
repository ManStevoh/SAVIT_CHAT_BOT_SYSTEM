<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CognitiveSimulation extends Model
{
    protected $fillable = [
        'company_id', 'scenario_type', 'inputs', 'scenarios', 'recommendation',
    ];

    protected $casts = [
        'inputs' => 'array',
        'scenarios' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
