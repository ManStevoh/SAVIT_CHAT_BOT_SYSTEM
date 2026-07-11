<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceExperiment extends Model
{
    protected $fillable = [
        'company_id', 'name', 'experiment_type', 'status', 'metric_key',
        'winner_variant_id', 'config', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'config' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(CommerceExperimentVariant::class, 'experiment_id');
    }

    public function winnerVariant(): BelongsTo
    {
        return $this->belongsTo(CommerceExperimentVariant::class, 'winner_variant_id');
    }
}
