<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutivePlan extends Model
{
    protected $fillable = [
        'company_id', 'goal_statement', 'breakdown', 'kpi_targets', 'status',
    ];

    protected $casts = [
        'breakdown' => 'array',
        'kpi_targets' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
