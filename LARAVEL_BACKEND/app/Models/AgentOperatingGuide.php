<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentOperatingGuide extends Model
{
    protected $fillable = [
        'company_id',
        'topic',
        'guidance',
        'source',
        'hit_count',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
