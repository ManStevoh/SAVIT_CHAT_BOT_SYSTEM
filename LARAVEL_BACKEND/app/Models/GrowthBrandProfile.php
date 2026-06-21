<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthBrandProfile extends Model
{
    protected $fillable = [
        'company_id',
        'winning_patterns',
        'content_mix_weights',
        'avg_metrics',
        'last_learned_at',
    ];

    protected $casts = [
        'winning_patterns' => 'array',
        'content_mix_weights' => 'array',
        'avg_metrics' => 'array',
        'last_learned_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
