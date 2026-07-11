<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessWorldSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['company_id', 'world_model', 'trigger', 'created_at'];

    protected $casts = [
        'world_model' => 'array',
        'created_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
